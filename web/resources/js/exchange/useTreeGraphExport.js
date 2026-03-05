/**
 * Export the category / code tree as a downloadable SVG image.
 *
 * Uses a compact vertical indented layout (like a file explorer).
 * Pure SVG generation — no canvas, no external libraries.
 */

const PADDING = 30;
const ROW_HEIGHT = 28;
const ROW_GAP = 4;
const INDENT = 24;
const NODE_RADIUS = 5;
const FONT_SIZE = 13;
const BADGE_FONT_SIZE = 10;
const TITLE_FONT_SIZE = 16;
const LINE_COLOR = '#d1d5db';
const TEXT_COLOR = '#1f2937';
const CODE_DOT_R = 5;
const UNCATEGORIZED_COLOR = '#9ca3af';
const CHAR_WIDTH = 7.5;
const BADGE_CHAR_WIDTH = 6;

// ---- Tree building ---------------------------------------------------

function buildTree({ categories, codes }) {
  const cats = Array.isArray(categories) ? categories : [];
  const cds = Array.isArray(codes) ? codes : [];

  const codeMap = new Map();
  const walk = (list) => {
    for (const c of list) {
      codeMap.set(c.id, c);
      if (c.children?.length) walk(c.children);
    }
  };
  walk(cds);

  const buildCat = (cat) => {
    const children = [];
    for (const child of cats.filter((c) => c.parent_id === cat.id)) {
      children.push(buildCat(child));
    }
    for (const ref of cat.codes ?? []) {
      const code = codeMap.get(ref.id);
      if (code) {
        children.push({
          label: code.name,
          color: code.color || '#ebebeb',
          type: 'code',
          badge: null,
          children: [],
        });
      }
    }
    return {
      label: cat.name,
      color: cat.color || '#6b7280',
      type: cat.type || 'category',
      badge: cat.type === 'theme' ? 'Theme' : 'Category',
      children,
    };
  };

  const roots = cats.filter((c) => !c.parent_id).map(buildCat);

  const catCodeIds = new Set();
  for (const cat of cats) {
    for (const c of cat.codes ?? []) catCodeIds.add(c.id);
  }
  const uncat = cds.filter((c) => !catCodeIds.has(c.id));
  if (uncat.length) {
    roots.push({
      label: 'Uncategorized',
      color: UNCATEGORIZED_COLOR,
      type: 'category',
      badge: null,
      children: uncat.map((c) => ({
        label: c.name,
        color: c.color || '#ebebeb',
        type: 'code',
        badge: null,
        children: [],
      })),
    });
  }

  return {
    label: 'Project',
    color: '#374151',
    type: 'root',
    badge: null,
    children: roots,
  };
}

// ---- Node width (for box sizing) ------------------------------------

function nodeWidth(node) {
  let w = node.label.length * CHAR_WIDTH + 24;
  if (node.badge) w += node.badge.length * BADGE_CHAR_WIDTH + 18;
  if (node.type === 'code') w += CODE_DOT_R * 2 + 10;
  return Math.max(w, 60);
}

// ---- Vertical indented layout ----------------------------------------
// Walks the tree depth-first, placing each node on its own row.
// Returns a flat list of { node, x, y, w, h, depth } entries.

function flattenTree(root) {
  const rows = [];
  let cursorY = PADDING + TITLE_FONT_SIZE + 20;

  function visit(node, depth) {
    const x = PADDING + depth * INDENT;
    const w = nodeWidth(node);
    const h = ROW_HEIGHT;
    node._x = x;
    node._y = cursorY;
    node._w = w;
    node._h = h;
    node._depth = depth;
    rows.push(node);
    cursorY += h + ROW_GAP;
    for (const ch of node.children) {
      visit(ch, depth + 1);
    }
  }

  visit(root, 0);
  return rows;
}

// ---- SVG helpers -----------------------------------------------------

function esc(s) {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function contrastColor(hex) {
  const c = (hex || '#888888').replace('#', '');
  const r = parseInt(c.substring(0, 2), 16);
  const g = parseInt(c.substring(2, 4), 16);
  const b = parseInt(c.substring(4, 6), 16);
  return (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.55
    ? '#1f2937'
    : '#ffffff';
}

function svgEdges(rows) {
  let s = '';
  for (const node of rows) {
    for (const ch of node.children) {
      // Vertical line down from parent, then horizontal to child
      const px = node._x + node._w / 2;
      const py = node._y + node._h;
      const cx = ch._x;
      const cy = ch._y + ch._h / 2;
      const midX = cx - 6; // connector arrives just left of child box
      s += `<path d="M${px},${py} L${px},${cy} L${midX},${cy}" fill="none" stroke="${LINE_COLOR}" stroke-width="1.2"/>`;
    }
  }
  return s;
}

function svgNode(node) {
  let s = '';
  const x = node._x;
  const y = node._y;
  const w = node._w;
  const h = node._h;

  if (node.type === 'root') {
    s += `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${NODE_RADIUS}" fill="#374151"/>`;
    s += `<text x="${x + w / 2}" y="${y + h / 2}" text-anchor="middle" dominant-baseline="central" fill="#fff" font-size="${FONT_SIZE}" font-weight="bold">${esc(node.label)}</text>`;
  } else if (node.type === 'code') {
    s += `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${NODE_RADIUS}" fill="#f9fafb" stroke="#e5e7eb"/>`;
    const dotCx = x + 10 + CODE_DOT_R;
    const dotCy = y + h / 2;
    s += `<circle cx="${dotCx}" cy="${dotCy}" r="${CODE_DOT_R}" fill="${esc(node.color)}"/>`;
    s += `<text x="${dotCx + CODE_DOT_R + 6}" y="${y + h / 2}" dominant-baseline="central" fill="${TEXT_COLOR}" font-size="${FONT_SIZE}">${esc(node.label)}</text>`;
  } else {
    // Category / Theme
    const fill = node.color || '#e5e7eb';
    const tc = contrastColor(fill);
    s += `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${NODE_RADIUS}" fill="${esc(fill)}" stroke="rgba(0,0,0,0.08)"/>`;
    const labelX = x + 10;
    s += `<text x="${labelX}" y="${y + h / 2}" dominant-baseline="central" fill="${tc}" font-size="${FONT_SIZE}" font-weight="600">${esc(node.label)}</text>`;

    if (node.badge) {
      const lw = node.label.length * CHAR_WIDTH;
      const bx = labelX + lw + 8;
      const bw = node.badge.length * BADGE_CHAR_WIDTH + 10;
      const bh = 16;
      const by = y + (h - bh) / 2;
      const isTheme = node.type === 'theme';
      const bbg = isTheme ? '#f3e8ff' : '#dbeafe';
      const btc = isTheme ? '#7c3aed' : '#1d4ed8';
      s += `<rect x="${bx}" y="${by}" width="${bw}" height="${bh}" rx="${bh / 2}" fill="${bbg}"/>`;
      s += `<text x="${bx + bw / 2}" y="${by + bh / 2}" text-anchor="middle" dominant-baseline="central" fill="${btc}" font-size="${BADGE_FONT_SIZE}" font-weight="600">${esc(node.badge)}</text>`;
    }
  }
  return s;
}

// ---- Public API ------------------------------------------------------

/**
 * Export the category/code tree as a downloadable SVG file.
 *
 * @param {Object} params
 * @param {Array} params.categories
 * @param {Array} params.codes
 * @param {string} params.projectName
 */
export function exportTreeGraphAsPNG({ categories, codes, projectName }) {
  const tree = buildTree({ categories, codes });
  tree.label = projectName || 'Project';

  const rows = flattenTree(tree);

  // Compute canvas size
  let maxRight = 0;
  let maxBottom = 0;
  for (const n of rows) {
    const r = n._x + n._w;
    const b = n._y + n._h;
    if (r > maxRight) maxRight = r;
    if (b > maxBottom) maxBottom = b;
  }
  const width = Math.ceil(maxRight + PADDING);
  const height = Math.ceil(maxBottom + PADDING);

  // Title
  const titleSvg = `<text x="${PADDING}" y="${PADDING}" dominant-baseline="central" fill="${TEXT_COLOR}" font-size="${TITLE_FONT_SIZE}" font-weight="bold">${esc(projectName || 'Project')}</text>`;

  // Build SVG
  const nodesSvg = rows.map(svgNode).join('\n');
  const edgesSvg = svgEdges(rows);

  const svg = [
    `<?xml version="1.0" encoding="UTF-8"?>`,
    `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" style="font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;">`,
    `<rect width="${width}" height="${height}" fill="#ffffff"/>`,
    titleSvg,
    edgesSvg,
    nodesSvg,
    `</svg>`,
  ].join('\n');

  const blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  const date = new Date().toLocaleDateString().replace(/[_.:,\s]+/g, '-');
  a.download = `${projectName || 'OpenQDA'} Tree ${date}.svg`;
  a.href = url;
  document.body.appendChild(a);
  a.click();
  setTimeout(() => {
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }, 200);
}
