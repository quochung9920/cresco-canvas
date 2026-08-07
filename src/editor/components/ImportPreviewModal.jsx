import React, { useState } from 'react';
import validateCrescoSession from '../../utils/validateCrescoSession';

export default function ImportPreviewModal({ onClose, onApply, onCreateCheckpoint }) {
  const [input, setInput] = useState('');
  const [result, setResult] = useState(null);

  function handleValidate() {
    try {
      const parsed = JSON.parse(input);
      const validation = validateCrescoSession(parsed);
      setResult({ parsed, validation });
    } catch (err) {
      setResult({ error: 'Invalid JSON: ' + err.message });
    }
  }

  function handleApply() {
    if (!result) return;
    if (result.validation && result.validation.valid) {
      // create a checkpoint if provided
      if (onCreateCheckpoint) onCreateCheckpoint('Import session from AI');
      if (onApply) onApply(result.parsed);
      if (onClose) onClose();
    } else {
      alert('Cannot apply: session is invalid. Check validation errors.');
    }
  }

  return (
    <div className="cc-import-modal" style={{ padding: 16, maxWidth: 800, margin: '0 auto' }}>
      <h2>Import Cresco Session (AI)</h2>
      <p>Paste a Cresco Session JSON below. Click "Validate" to preview and check errors before applying.</p>
      <textarea
        value={input}
        onChange={(e) => setInput(e.target.value)}
        rows={12}
        style={{ width: '100%', fontFamily: 'monospace' }}
        placeholder="Paste Cresco Session JSON here"
      />

      <div style={{ marginTop: 8 }}>
        <button onClick={handleValidate}>Validate & Preview</button>
        <button onClick={handleApply} style={{ marginLeft: 8 }}>Apply (create checkpoint)</button>
        <button onClick={onClose} style={{ marginLeft: 8 }}>Close</button>
      </div>

      <div style={{ marginTop: 12 }}>
        {result && result.error && (
          <div style={{ color: 'red' }}>
            <strong>Error:</strong> {result.error}
          </div>
        )}

        {result && result.validation && (
          <div>
            <h3>Validation</h3>
            <div>Valid: {result.validation.valid ? 'Yes' : 'No'}</div>
            {result.validation.errors && result.validation.errors.length > 0 && (
              <ul>
                {result.validation.errors.map((e, i) => (
                  <li key={i}>{e}</li>
                ))}
              </ul>
            )}

            <h3>Preview (trimmed)</h3>
            <pre style={{ maxHeight: 200, overflow: 'auto', background: '#f7f7f7', padding: 8 }}>
              {JSON.stringify(result.parsed, null, 2)}
            </pre>
          </div>
        )}
      </div>
    </div>
  );
}
