export default {
  key: 'codetree',
  name: 'CodeTreeView',
  title: 'Category & Code Tree',
  type: 'visualization',
  load: () => import('./CodeTreeView.vue'),
  hasOptions: true,
};
