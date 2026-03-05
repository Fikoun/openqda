export default {
  key: 'categorytree',

  /**
   * Component name
   */
  name: 'CategoryTreeView',

  /**
   * Human-readable title
   */
  title: 'Category / Theme Tree',

  /**
   * For filtering
   */
  type: 'visualization',

  /**
   * Load Vue component
   */
  load: () => import('./CategoryTreeView.vue'),

  /**
   * No options menu needed
   */
  hasOptions: false,
};
