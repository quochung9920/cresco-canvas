// Basic validator for Cresco Session POC
// This is intentionally lightweight for the POC. Replace with a full JSON Schema validator in production.

export default function validateCrescoSession(session) {
  const errors = [];

  if (!session || typeof session !== 'object') {
    errors.push('Session must be an object');
    return { valid: false, errors };
  }

  // Check for common identifiers used by Cresco Session v1
  if (session.schema && typeof session.schema === 'string') {
    if (!session.schema.toLowerCase().includes('cresco-session')) {
      errors.push('Unknown schema format: ' + session.schema);
    }
  } else if (session.type && typeof session.type === 'string') {
    if (!session.type.toLowerCase().includes('cresco')) {
      errors.push('Unknown type field: ' + session.type);
    }
  } else {
    errors.push('Missing schema or type field (expecting "cresco-session" schema)');
  }

  // Basic shape checks
  if (!session.widgets && !session.structure && !session.pages) {
    errors.push('Session does not contain expected keys: widgets / structure / pages');
  }

  return { valid: errors.length === 0, errors };
}
