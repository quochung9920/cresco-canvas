import validateCrescoSession from '../../utils/validateCrescoSession';

export function parseAndValidate(jsonString) {
  try {
    const parsed = JSON.parse(jsonString);
    const validation = validateCrescoSession(parsed);
    return { parsed, validation };
  } catch (err) {
    return { error: 'Invalid JSON: ' + err.message };
  }
}

export default parseAndValidate;
