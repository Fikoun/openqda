import List from './src/list';
import Portrait from './src/portrait';
import WordCloud from './src/cloud';
import BarChart from './src/bar';
import CategoryTree from './src/categorytree';
import CodeTree from './src/codetree';

/**
 * passes all default visualization plugins
 * to the given plugin api.
 */
export default function register(api) {
  [List, Portrait, WordCloud, BarChart, CategoryTree, CodeTree].forEach(
    (plugin) => api.register(plugin)
  );
}
