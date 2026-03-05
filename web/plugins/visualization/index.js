import List from './src/list';
import Portrait from './src/portrait';
import WordCloud from './src/cloud';
import BarChart from './src/bar';
import CategoryTree from './src/categorytree';

/**
 * passes all default visualization plugins
 * to the given plugin api.
 */
export default function register(api) {
  [List, Portrait, WordCloud, BarChart, CategoryTree].forEach((plugin) =>
    api.register(plugin)
  );
}
