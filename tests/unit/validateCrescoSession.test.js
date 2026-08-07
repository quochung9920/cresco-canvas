// Basic unit test for validateCrescoSession POC

import validateCrescoSession from '../../src/utils/validateCrescoSession';

test('validates a minimal cresco session', () => {
  const session = { schema: 'cresco-session/v1', pages: [] };
  const res = validateCrescoSession(session);
  expect(res.valid).toBe(true);
});

test('rejects missing schema', () => {
  const session = { pages: [] };
  const res = validateCrescoSession(session);
  expect(res.valid).toBe(false);
  expect(res.errors.length).toBeGreaterThan(0);
});
