import { randomColor } from '../../utils/random/randomColor.js';

export const createCodeSchema = ({
  title,
  description,
  color,
  codebooks,
  codes,
  parent,
  codebookId,
}) => {
  const schema = {
    title: {
      type: String,
      placeholder: 'Name of the code',
      defaultValue: title,
    },
    description: {
      type: String,
      placeholder: 'Code description, optional',
      formType: 'textarea',
      defaultValue: description,
    },
    color: {
      type: String,
      formType: 'color',
      defaultValue: color ?? randomColor({ type: 'hex', opacity: -1 }),
    },
  };
  if (codebooks) {
    schema.codebookId = {
      type: Number,
      label: 'Codebook',
      defaultValue: codebookId ?? codebooks?.[0]?.id,
      options: codebooks?.map((c) => ({
        value: c.id,
        label: c.name,
      })),
    };
  }
  if (codes) {
    // Filter codes by the selected/default codebook
    const selectedCodebookId = codebookId ?? codebooks?.[0]?.id;
    const filteredCodes = selectedCodebookId 
      ? codes.filter(c => c.codebook === selectedCodebookId)
      : codes;
    
    schema.parentId = {
      type: String,
      optional: true,
      label: 'Parent code (optional)',
      options: [
        { value: '', label: '-- No parent --' },
        ...filteredCodes.map((c) => ({
          value: c.id,
          label: c.name,
        }))
      ],
    };
    if (parent) {
      schema.parentId.defaultValue = parent.id;
    }
  }
  return schema;
};
