import { request } from '../../utils/http/BackendRequest.js';

/**
 * Frontend API service for interacting with the Category backend endpoints.
 */
export const Categories = {};

/**
 * Fetch all categories for a project.
 * @param {object} params
 * @param {number|string} params.projectId
 * @returns {Promise<import('../../utils/http/BackendRequest.js').BackendRequest>}
 */
Categories.index = ({ projectId }) => {
  return request({
    url: route('category.index', { project: projectId }),
    type: 'get',
  });
};

/**
 * Create a new category and optionally attach codes.
 * @param {object} params
 * @param {number|string} params.projectId
 * @param {string} params.name
 * @param {string} [params.description]
 * @param {string} [params.color]
 * @param {string} [params.type] - 'category' or 'theme'
 * @param {number|null} [params.parentId]
 * @param {string[]} [params.codeIds]
 * @returns {Promise<import('../../utils/http/BackendRequest.js').BackendRequest>}
 */
Categories.create = ({ projectId, name, description, color, type, parentId, codeIds }) => {
  const body = { name };
  if (description) body.description = description;
  if (color) body.color = color;
  if (type) body.type = type;
  if (parentId) body.parent_id = parentId;
  if (codeIds && codeIds.length) body.code_ids = codeIds;

  return request({
    url: route('category.store', { project: projectId }),
    type: 'post',
    body,
  });
};

/**
 * Update a category.
 * @param {object} params
 * @param {number|string} params.projectId
 * @param {number|string} params.categoryId
 * @param {object} params.data - Fields to update (name, description, color, type, parent_id)
 * @returns {Promise<import('../../utils/http/BackendRequest.js').BackendRequest>}
 */
Categories.update = ({ projectId, categoryId, data }) => {
  return request({
    url: route('category.update', { project: projectId, category: categoryId }),
    type: 'patch',
    body: data,
  });
};

/**
 * Delete a category.
 * @param {object} params
 * @param {number|string} params.projectId
 * @param {number|string} params.categoryId
 * @returns {Promise<import('../../utils/http/BackendRequest.js').BackendRequest>}
 */
Categories.destroy = ({ projectId, categoryId }) => {
  return request({
    url: route('category.destroy', { project: projectId, category: categoryId }),
    type: 'delete',
  });
};

/**
 * Attach codes to an existing category.
 * @param {object} params
 * @param {number|string} params.projectId
 * @param {number|string} params.categoryId
 * @param {string[]} params.codeIds
 * @returns {Promise<import('../../utils/http/BackendRequest.js').BackendRequest>}
 */
Categories.attachCodes = ({ projectId, categoryId, codeIds }) => {
  return request({
    url: route('category.attach-codes', { project: projectId, category: categoryId }),
    type: 'post',
    body: { code_ids: codeIds },
  });
};

/**
 * Detach codes from a category.
 * @param {object} params
 * @param {number|string} params.projectId
 * @param {number|string} params.categoryId
 * @param {string[]} params.codeIds
 * @returns {Promise<import('../../utils/http/BackendRequest.js').BackendRequest>}
 */
Categories.detachCodes = ({ projectId, categoryId, codeIds }) => {
  return request({
    url: route('category.detach-codes', { project: projectId, category: categoryId }),
    type: 'post',
    body: { code_ids: codeIds },
  });
};
