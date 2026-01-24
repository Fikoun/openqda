<?php

namespace App\Console\Commands;

use App\Models\Code;
use App\Models\Codebook;
use App\Models\Project;
use App\Models\Selection;
use App\Models\Source;
use App\Models\Team;
use App\Models\User;
use App\Models\Variable;
use OwenIt\Auditing\Models\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportBackupData extends Command
{
    protected $signature = 'backup:import 
                            {path=./backup : Path to backup directory}
                            {--current-user : Attach all data to current user instead of importing users}
                            {--user= : Specify user ID or email to attach data to}';

    protected $description = 'Import data from external QDA provider backup';

    private $backupPath;

    private $idMappings = [];

    private $currentUserId = null;

    private $skipUserImport = false;

    private $stats = [
        'users' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
        'teams' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
        'projects' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
        'codebooks' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
        'codes' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
        'sources' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
        'variables' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
        'selections' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
        'audits' => ['success' => 0, 'skipped' => 0, 'failed' => 0],
    ];

    public function handle()
    {
        $this->backupPath = rtrim($this->argument('path'), '/');

        if (! is_dir($this->backupPath)) {
            $this->error("Backup directory not found: {$this->backupPath}");
            Log::error('Backup import failed: directory not found', ['path' => $this->backupPath]);

            return 1;
        }

        // Determine user mode
        if ($this->option('current-user') || $this->option('user')) {
            $this->skipUserImport = true;
            
            if ($this->option('user')) {
                $user = $this->findUser($this->option('user'));
                if (! $user) {
                    $this->error('User not found: '.$this->option('user'));
                    return 1;
                }
                $this->currentUserId = $user->id;
                $this->info("Attaching all data to user: {$user->name} ({$user->email})");
            } else {
                $this->currentUserId = $this->askForUser();
                if (! $this->currentUserId) {
                    return 1;
                }
            }
        }

        $this->info('Starting backup import from: '.$this->backupPath);
        $this->info('Logs will be written to storage/logs/laravel.log');
        $this->newLine();

        // Import in dependency order
        $this->importUsers();
        $this->importTeams();
        $this->importProjects();
        $this->importCodebooks();
        $this->importCodes();
        $this->importSources();
        $this->importVariables();
        $this->importSelections();
        $this->importAudits();

        $this->newLine();
        $this->displaySummary();

        return 0;
    }

    private function loadJson($filename)
    {
        $path = "{$this->backupPath}/{$filename}";

        if (! file_exists($path)) {
            $this->warn("File not found: {$filename} - skipping");
            Log::warning('Backup file not found', ['file' => $filename, 'path' => $path]);

            return [];
        }

        try {
            $content = file_get_contents($path);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Invalid JSON in {$filename}: ".json_last_error_msg());
                Log::error('Invalid JSON in backup file', [
                    'file' => $filename,
                    'error' => json_last_error_msg(),
                ]);

                return [];
            }

            return $data ?? [];
        } catch (\Exception $e) {
            $this->error("Failed to read {$filename}: ".$e->getMessage());
            Log::error('Failed to read backup file', [
                'file' => $filename,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function findUser($identifier)
    {
        // Try by ID first
        if (is_numeric($identifier)) {
            $user = User::find($identifier);
            if ($user) {
                return $user;
            }
        }

        // Try by email
        return User::where('email', $identifier)->first();
    }

    private function askForUser()
    {
        $users = User::orderBy('name')->get();

        if ($users->isEmpty()) {
            $this->error('No users found in the system. Please create a user first.');
            return null;
        }

        $this->info('Available users:');
        foreach ($users as $index => $user) {
            $this->line(sprintf('  [%d] %s (%s)', $index + 1, $user->name, $user->email));
        }

        $choice = $this->ask('Select user number to attach data to');

        if (! is_numeric($choice) || $choice < 1 || $choice > $users->count()) {
            $this->error('Invalid selection');
            return null;
        }

        $user = $users[$choice - 1];
        $this->info("Selected: {$user->name} ({$user->email})");

        return $user->id;
    }

    private function importUsers()
    {
        if ($this->skipUserImport) {
            $this->info('Skipping user import - mapping all to current user');
            $users = $this->loadJson('users.json');
            
            // Map all old user IDs to the current user
            foreach ($users as $userData) {
                $this->idMappings['users'][$userData['id']] = $this->currentUserId;
                $this->stats['users']['skipped']++;
            }
            
            $this->line("  Mapped {$this->stats['users']['skipped']} users to current user");
            return;
        }

        $this->info('Importing users...');
        $users = $this->loadJson('users.json');
        
        $importedEmails = [];

        foreach ($users as $userData) {
            try {
                // Validate required fields
                if (empty($userData['email'])) {
                    $this->stats['users']['failed']++;
                    Log::warning('User missing email', ['data' => $userData]);
                    continue;
                }

                // Check if user already exists by email
                $existingUser = User::where('email', $userData['email'])->first();

                if ($existingUser) {
                    $this->idMappings['users'][$userData['id']] = $existingUser->id;
                    $this->stats['users']['skipped']++;
                    $this->line("  Exists: {$userData['email']}");
                    continue;
                }

                $user = User::create([
                    'name' => $userData['name'] ?? 'Imported User',
                    'email' => $userData['email'],
                    'password' => bcrypt(Str::random(32)),
                    'email_verified_at' => $userData['email_verified_at'] ?? null,
                    'created_at' => $userData['created_at'] ?? now(),
                    'updated_at' => $userData['updated_at'] ?? now(),
                ]);

                $this->idMappings['users'][$userData['id']] = $user->id;
                $this->stats['users']['success']++;
                $this->line("  ✓ Created: {$user->email} (needs password reset)");
                $importedEmails[] = $user->email;

                Log::info('User imported', ['id' => $user->id, 'email' => $user->email]);
            } catch (\Exception $e) {
                $this->stats['users']['failed']++;
                $this->error("  ✗ Failed to import user: ".$e->getMessage());
                Log::error('User import failed', [
                    'data' => $userData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! empty($importedEmails)) {
            $this->newLine();
            $this->warn('⚠ Imported users have random passwords and cannot log in.');
            $this->warn('  Send password reset emails to: '.implode(', ', $importedEmails));
        }
    }

    private function importTeams()
    {
        $this->info('Importing teams...');
        $teams = $this->loadJson('teams.json');

        foreach ($teams as $teamData) {
            try {
                if (empty($teamData['name'])) {
                    $this->stats['teams']['failed']++;
                    Log::warning('Team missing name', ['data' => $teamData]);
                    continue;
                }

                $userId = null;
                if (isset($teamData['user_id'])) {
                    $userId = $this->idMappings['users'][$teamData['user_id']] ?? null;
                    if (! $userId) {
                        $this->stats['teams']['failed']++;
                        $this->error("  ✗ Team '{$teamData['name']}': user not found");
                        Log::warning('Team user not found', ['team' => $teamData['name']]);
                        continue;
                    }
                }

                $team = Team::create([
                    'name' => $teamData['name'],
                    'personal_team' => $teamData['personal_team'] ?? false,
                    'user_id' => $userId,
                    'created_at' => $teamData['created_at'] ?? now(),
                    'updated_at' => $teamData['updated_at'] ?? now(),
                ]);

                $this->idMappings['teams'][$teamData['id']] = $team->id;
                $this->stats['teams']['success']++;
                $this->line("  ✓ Created: {$team->name}");

                Log::info('Team imported', ['id' => $team->id, 'name' => $team->name]);
            } catch (\Exception $e) {
                $this->stats['teams']['failed']++;
                $this->error("  ✗ Failed to import team: ".$e->getMessage());
                Log::error('Team import failed', [
                    'data' => $teamData,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function importProjects()
    {
        $this->info('Importing projects...');
        $projects = $this->loadJson('projects.json');

        foreach ($projects as $projectData) {
            try {
                if (empty($projectData['name'])) {
                    $this->stats['projects']['failed']++;
                    Log::warning('Project missing name', ['data' => $projectData]);
                    continue;
                }

                $teamId = null;
                if (isset($projectData['team_id'])) {
                    $teamId = $this->idMappings['teams'][$projectData['team_id']] ?? null;
                }

                $userId = null;
                if (isset($projectData['creating_user_id'])) {
                    $userId = $this->idMappings['users'][$projectData['creating_user_id']] ?? null;
                }

                $project = Project::create([
                    'name' => $projectData['name'],
                    'description' => $projectData['description'] ?? null,
                    'team_id' => $teamId,
                    'creating_user_id' => $userId,
                    'created_at' => $projectData['created_at'] ?? now(),
                    'updated_at' => $projectData['updated_at'] ?? now(),
                ]);

                $this->idMappings['projects'][$projectData['id']] = $project->id;
                $this->stats['projects']['success']++;
                $this->line("  ✓ Created: {$project->name}");

                Log::info('Project imported', ['id' => $project->id, 'name' => $project->name]);
            } catch (\Exception $e) {
                $this->stats['projects']['failed']++;
                $this->error("  ✗ Failed to import project: ".$e->getMessage());
                Log::error('Project import failed', [
                    'data' => $projectData,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function importCodebooks()
    {
        $this->info('Importing codebooks...');
        $codebooks = $this->loadJson('codebooks.json');

        foreach ($codebooks as $codebookData) {
            try {
                if (empty($codebookData['name'])) {
                    $this->stats['codebooks']['failed']++;
                    Log::warning('Codebook missing name', ['data' => $codebookData]);
                    continue;
                }

                $projectId = null;
                if (isset($codebookData['project_id'])) {
                    $projectId = $this->idMappings['projects'][$codebookData['project_id']] ?? null;
                }

                $creatingUserId = null;
                if (isset($codebookData['creating_user_id'])) {
                    $creatingUserId = $this->idMappings['users'][$codebookData['creating_user_id']] ?? null;
                } elseif (isset($codebookData['user_id'])) {
                    $creatingUserId = $this->idMappings['users'][$codebookData['user_id']] ?? null;
                }
                
                // Fall back to current user if no user mapping exists
                $creatingUserId = $creatingUserId ?? $this->currentUserId ?? auth()->id();

                $codebook = Codebook::create([
                    'name' => $codebookData['name'],
                    'description' => $codebookData['description'] ?? null,
                    'project_id' => $projectId,
                    'properties' => $codebookData['properties'] ?? null,
                    'creating_user_id' => $creatingUserId,
                    'created_at' => $codebookData['created_at'] ?? now(),
                    'updated_at' => $codebookData['updated_at'] ?? now(),
                ]);

                $this->idMappings['codebooks'][$codebookData['id']] = $codebook->id;
                $this->stats['codebooks']['success']++;
                $this->line("  ✓ Created: {$codebook->name} (old ID: {$codebookData['id']} -> new ID: {$codebook->id})");

                Log::info('Codebook imported', [
                    'id' => $codebook->id, 
                    'name' => $codebook->name,
                    'old_id' => $codebookData['id'],
                    'mapping_created' => $this->idMappings['codebooks'][$codebookData['id']],
                ]);
            } catch (\Exception $e) {
                $this->stats['codebooks']['failed']++;
                $this->error("  ✗ Failed to import codebook: ".$e->getMessage());
                Log::error('Codebook import failed', [
                    'data' => $codebookData,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function importCodes()
    {
        $this->info('Importing codes...');
        $codes = $this->loadJson('codes.json');

        // Import codes in two passes to handle parent relationships
        $codesWithParents = [];

        foreach ($codes as $codeData) {
            try {
                if (empty($codeData['name'])) {
                    $this->stats['codes']['failed']++;
                    Log::warning('Code missing name', ['data' => $codeData]);
                    continue;
                }

                $codebookId = null;
                if (isset($codeData['codebook_id'])) {
                    $codebookId = $this->idMappings['codebooks'][$codeData['codebook_id']] ?? null;
                    
                    if (! $codebookId) {
                        $this->stats['codes']['failed']++;
                        $this->error("  ✗ Code '{$codeData['name']}': codebook {$codeData['codebook_id']} not found in mappings");
                        Log::warning('Code codebook not found', [
                            'code' => $codeData['name'],
                            'codebook_id' => $codeData['codebook_id'],
                            'available_mappings' => array_keys($this->idMappings['codebooks'] ?? []),
                        ]);
                        continue;
                    }
                }

                // First pass: import codes without parent_id
                if (! isset($codeData['parent_id']) || empty($codeData['parent_id'])) {
                    $code = new Code([
                        'name' => $codeData['name'],
                        'description' => $codeData['description'] ?? null,
                        'color' => $codeData['color'] ?? '#000000',
                        'parent_id' => null,
                    ]);
                    $code->codebook_id = $codebookId;
                    $code->save();

                    $this->idMappings['codes'][$codeData['id']] = $code->id;
                    $this->stats['codes']['success']++;
                    $this->line("  ✓ Created: {$code->name}");

                    Log::info('Code imported', ['id' => $code->id, 'name' => $code->name]);
                } else {
                    $codesWithParents[] = $codeData;
                }
            } catch (\Exception $e) {
                $this->stats['codes']['failed']++;
                $this->error("  ✗ Failed to import code: ".$e->getMessage());
                Log::error('Code import failed', [
                    'data' => $codeData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Second pass: import codes with parent_id
        foreach ($codesWithParents as $codeData) {
            try {
                $codebookId = null;
                if (isset($codeData['codebook_id'])) {
                    $codebookId = $this->idMappings['codebooks'][$codeData['codebook_id']] ?? null;
                }

                $parentId = null;
                if (isset($codeData['parent_id'])) {
                    $parentId = $this->idMappings['codes'][$codeData['parent_id']] ?? null;
                }

                $code = new Code([
                    'name' => $codeData['name'],
                    'description' => $codeData['description'] ?? null,
                    'color' => $codeData['color'] ?? '#000000',
                    'parent_id' => $parentId,
                ]);
                $code->codebook_id = $codebookId;
                $code->save();

                $this->idMappings['codes'][$codeData['id']] = $code->id;
                $this->stats['codes']['success']++;
                $this->line("  ✓ Created: {$code->name}");

                Log::info('Code imported', ['id' => $code->id, 'name' => $code->name]);
            } catch (\Exception $e) {
                $this->stats['codes']['failed']++;
                $this->error("  ✗ Failed to import code: ".$e->getMessage());
                Log::error('Code import failed', [
                    'data' => $codeData,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function importSources()
    {
        $this->info('Importing sources...');
        $sources = $this->loadJson('sources.json');

        foreach ($sources as $sourceData) {
            try {
                if (empty($sourceData['name'])) {
                    $this->stats['sources']['failed']++;
                    Log::warning('Source missing name', ['data' => $sourceData]);
                    continue;
                }

                $projectId = null;
                if (isset($sourceData['project_id'])) {
                    $projectId = $this->idMappings['projects'][$sourceData['project_id']] ?? null;
                }

                $userId = null;
                if (isset($sourceData['user_id'])) {
                    $userId = $this->idMappings['users'][$sourceData['user_id']] ?? null;
                }

                $creatingUserId = $userId ?? $this->currentUserId ?? auth()->id();

                $source = Source::create([
                    'name' => $sourceData['name'],
                    'content' => $sourceData['content'] ?? null,
                    'type' => $sourceData['type'] ?? 'text',
                    'project_id' => $projectId,
                    'creating_user_id' => $creatingUserId,
                    'modifying_user_id' => $creatingUserId,
                    'upload_path' => $sourceData['upload_path'] ?? null,
                    'created_at' => $sourceData['created_at'] ?? now(),
                    'updated_at' => $sourceData['updated_at'] ?? now(),
                ]);

                // Try to import HTML content if available
                $this->importSourceFiles($source, $sourceData);

                $this->idMappings['sources'][$sourceData['id']] = $source->id;
                $this->stats['sources']['success']++;
                $this->line("  ✓ Created: {$source->name}");

                Log::info('Source imported', ['id' => $source->id, 'name' => $source->name]);
            } catch (\Exception $e) {
                $this->stats['sources']['failed']++;
                $this->error("  ✗ Failed to import source: ".$e->getMessage());
                Log::error('Source import failed', [
                    'data' => $sourceData,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function importSourceFiles($source, $sourceData)
    {
        try {
            $projectFolder = "{$this->backupPath}/{$sourceData['project_id']}/sources";

            if (! is_dir($projectFolder)) {
                return;
            }

            // Build list of possible base names (without extension)
            $possibleNames = [];
            
            // From name field - strip any extension first
            if (! empty($sourceData['name'])) {
                $name = $sourceData['name'];
                // Remove common extensions
                $name = preg_replace('/\.(txt|html|docx?|pdf|rtf|odt)$/i', '', $name);
                $possibleNames[] = $name;
                // Also try with special chars replaced
                $possibleNames[] = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $name);
            }
            
            // From upload_path - get the basename without extension
            if (! empty($sourceData['upload_path'])) {
                $uploadBasename = pathinfo($sourceData['upload_path'], PATHINFO_FILENAME);
                $possibleNames[] = $uploadBasename;
            }
            
            // From original_name if present
            if (! empty($sourceData['original_name'])) {
                $originalName = preg_replace('/\.(txt|html|docx?|pdf|rtf|odt)$/i', '', $sourceData['original_name']);
                $possibleNames[] = $originalName;
            }
            
            // Remove duplicates and empty values
            $possibleNames = array_unique(array_filter($possibleNames));

            foreach ($possibleNames as $baseName) {
                $htmlPath = "{$projectFolder}/{$baseName}.html";

                if (file_exists($htmlPath)) {
                    $htmlContent = file_get_contents($htmlPath);
                    $storagePath = "projects/{$source->project_id}/sources/{$source->id}/converted.html";
                    $fullPath = Storage::path($storagePath);
                    
                    // Ensure directory exists
                    $directory = dirname($fullPath);
                    if (! is_dir($directory)) {
                        mkdir($directory, 0755, true);
                    }
                    
                    Storage::put($storagePath, $htmlContent);

                    // Create SourceStatus record so source shows as converted
                    \App\Models\SourceStatus::updateOrCreate(
                        ['source_id' => $source->id],
                        [
                            'status' => 'converted:html',
                            'path' => $fullPath,
                        ]
                    );

                    $this->line("    + Imported HTML content");
                    Log::info('Source HTML imported', [
                        'source_id' => $source->id,
                        'path' => $storagePath,
                    ]);
                    break;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to import source files', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function importVariables()
    {
        $this->info('Importing variables...');
        $variables = $this->loadJson('variables.json');

        foreach ($variables as $variableData) {
            try {
                if (empty($variableData['name'])) {
                    $this->stats['variables']['failed']++;
                    Log::warning('Variable missing name', ['data' => $variableData]);
                    continue;
                }

                $sourceId = null;
                if (isset($variableData['source_id'])) {
                    $sourceId = $this->idMappings['sources'][$variableData['source_id']] ?? null;
                    if (! $sourceId) {
                        $this->stats['variables']['skipped']++;
                        continue;
                    }
                }

                $type = $variableData['type'] ?? $variableData['type_of_variable'] ?? 'text';
                
                Variable::create([
                    'name' => $variableData['name'],
                    'type_of_variable' => $type,
                    'description' => $variableData['description'] ?? null,
                    'text_value' => $variableData['text_value'] ?? $variableData['string_value'] ?? null,
                    'boolean_value' => $variableData['boolean_value'] ?? null,
                    'integer_value' => $variableData['integer_value'] ?? null,
                    'float_value' => $variableData['float_value'] ?? null,
                    'date_value' => $variableData['date_value'] ?? null,
                    'datetime_value' => $variableData['datetime_value'] ?? null,
                    'source_id' => $sourceId,
                    'created_at' => $variableData['created_at'] ?? now(),
                    'updated_at' => $variableData['updated_at'] ?? now(),
                ]);

                $this->stats['variables']['success']++;
            } catch (\Exception $e) {
                $this->stats['variables']['failed']++;
                Log::error('Variable import failed', [
                    'data' => $variableData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($this->stats['variables']['success'] > 0) {
            $this->line("  ✓ Imported {$this->stats['variables']['success']} variables");
        }
    }

    private function importSelections()
    {
        $this->info('Importing selections...');
        $selections = $this->loadJson('selections.json');

        foreach ($selections as $selectionData) {
            try {
                $sourceId = null;
                if (isset($selectionData['source_id'])) {
                    $sourceId = $this->idMappings['sources'][$selectionData['source_id']] ?? null;
                    if (! $sourceId) {
                        $this->stats['selections']['skipped']++;
                        continue;
                    }
                }

                $codeId = null;
                if (isset($selectionData['code_id'])) {
                    $codeId = $this->idMappings['codes'][$selectionData['code_id']] ?? null;
                    if (! $codeId) {
                        $this->stats['selections']['skipped']++;
                        continue;
                    }
                }

                $userId = null;
                if (isset($selectionData['creating_user_id'])) {
                    $userId = $this->idMappings['users'][$selectionData['creating_user_id']] ?? null;
                } elseif (isset($selectionData['user_id'])) {
                    $userId = $this->idMappings['users'][$selectionData['user_id']] ?? null;
                }
                
                $userId = $userId ?? $this->currentUserId ?? auth()->id();
                
                $projectId = null;
                if (isset($selectionData['project_id'])) {
                    $projectId = $this->idMappings['projects'][$selectionData['project_id']] ?? null;
                }

                Selection::create([
                    'text' => $selectionData['text'] ?? '',
                    'description' => $selectionData['description'] ?? null,
                    'start_position' => $selectionData['start_position'] ?? 0,
                    'end_position' => $selectionData['end_position'] ?? 0,
                    'source_id' => $sourceId,
                    'code_id' => $codeId,
                    'project_id' => $projectId,
                    'creating_user_id' => $userId,
                    'modifying_user_id' => $userId,
                    'created_at' => $selectionData['created_at'] ?? now(),
                    'updated_at' => $selectionData['updated_at'] ?? now(),
                ]);

                $this->stats['selections']['success']++;
            } catch (\Exception $e) {
                $this->stats['selections']['failed']++;
                Log::error('Selection import failed', [
                    'data' => $selectionData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($this->stats['selections']['success'] > 0) {
            $this->line("  ✓ Imported {$this->stats['selections']['success']} selections");
        }
    }

    private function importAudits()
    {
        $this->info('Importing audits...');
        $audits = $this->loadJson('audits.json');

        foreach ($audits as $auditData) {
            try {
                $userId = null;
                if (isset($auditData['user_id'])) {
                    $userId = $this->idMappings['users'][$auditData['user_id']] ?? null;
                }

                Audit::create([
                    'user_type' => $auditData['user_type'] ?? User::class,
                    'user_id' => $userId,
                    'event' => $auditData['event'] ?? 'imported',
                    'auditable_type' => $auditData['auditable_type'] ?? null,
                    'auditable_id' => $this->mapAuditableId($auditData),
                    'old_values' => $auditData['old_values'] ?? null,
                    'new_values' => $auditData['new_values'] ?? null,
                    'url' => $auditData['url'] ?? null,
                    'ip_address' => $auditData['ip_address'] ?? null,
                    'user_agent' => $auditData['user_agent'] ?? null,
                    'tags' => $auditData['tags'] ?? null,
                    'created_at' => $auditData['created_at'] ?? now(),
                    'updated_at' => $auditData['updated_at'] ?? now(),
                ]);

                $this->stats['audits']['success']++;
            } catch (\Exception $e) {
                // Skip audits that fail - they often contain old IDs in JSON that don't exist
                $this->stats['audits']['skipped']++;
                Log::debug('Audit skipped', [
                    'event' => $auditData['event'] ?? 'unknown',
                    'auditable_type' => $auditData['auditable_type'] ?? 'unknown',
                ]);
            }
        }

        if ($this->stats['audits']['success'] > 0) {
            $this->line("  ✓ Imported {$this->stats['audits']['success']} audits");
        }
    }

    private function mapAuditableId($auditData)
    {
        if (! isset($auditData['auditable_type']) || ! isset($auditData['auditable_id'])) {
            return null;
        }

        $type = $auditData['auditable_type'];
        $oldId = $auditData['auditable_id'];

        $mapping = [
            'User' => 'users',
            'Project' => 'projects',
            'Source' => 'sources',
            'Code' => 'codes',
            'Codebook' => 'codebooks',
            'Selection' => 'selections',
        ];

        foreach ($mapping as $class => $key) {
            if (str_contains($type, $class)) {
                return $this->idMappings[$key][$oldId] ?? null;
            }
        }

        return null;
    }

    private function displaySummary()
    {
        $this->info('Import Summary:');
        $this->info('═══════════════════════════════════════');

        foreach ($this->stats as $entity => $counts) {
            $total = $counts['success'] + $counts['skipped'] + $counts['failed'];
            if ($total > 0) {
                $this->line(sprintf(
                    '%-12s: %3d success, %3d skipped, %3d failed',
                    ucfirst($entity),
                    $counts['success'],
                    $counts['skipped'],
                    $counts['failed']
                ));
            }
        }

        $this->info('═══════════════════════════════════════');

        $totalSuccess = array_sum(array_column($this->stats, 'success'));
        $totalFailed = array_sum(array_column($this->stats, 'failed'));

        if ($totalFailed > 0) {
            $this->warn("Import completed with {$totalFailed} failures. Check logs for details.");
        } else {
            $this->info("Import completed successfully! {$totalSuccess} items imported.");
        }
    }
}
