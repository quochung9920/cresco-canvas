import React from 'react';
import basic from '../../../templates/basic.json';
import marketing from '../../../templates/marketing.json';
import blog from '../../../templates/blog.json';

const TEMPLATES = [
  { id: 'basic', title: basic.title || 'Basic', preview: basic },
  { id: 'marketing', title: marketing.title || 'Marketing', preview: marketing },
  { id: 'blog', title: blog.title || 'Blog', preview: blog }
];

export default function TemplateManager({ onImport }) {
  function handleImport(template) {
    if (onImport) onImport(template.preview);
  }

  return (
    <div className="cc-template-manager" style={{ padding: 12 }}>
      <h3>Templates</h3>
      <div style={{ display: 'flex', gap: 12 }}>
        {TEMPLATES.map((t) => (
          <div key={t.id} style={{ border: '1px solid #ddd', padding: 8, width: 220 }}>
            <div style={{ fontWeight: '600' }}>{t.title}</div>
            <pre style={{ height: 100, overflow: 'auto', background: '#fafafa', padding: 6 }}>{JSON.stringify(t.preview.pages?.[0] || {}, null, 2)}</pre>
            <button onClick={() => handleImport(t)} style={{ marginTop: 6 }}>Import</button>
          </div>
        ))}
      </div>
    </div>
  );
}
