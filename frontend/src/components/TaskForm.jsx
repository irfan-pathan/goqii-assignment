import { useState } from 'react';

export const STATUSES = ['todo', 'in-progress', 'done'];
export const PRIORITIES = ['low', 'medium', 'high'];

// Mirrors Validator::task on the server (the server remains the source of truth).
function validate(f) {
  const errors = {};
  if (!f.title.trim() || f.title.trim().length > 150) errors.title = 'Title is required (max 150 characters).';
  if (f.description.length > 5000) errors.description = 'Description is too long (max 5000 characters).';
  if (f.due_date && !/^\d{4}-\d{2}-\d{2}$/.test(f.due_date)) errors.due_date = 'Use a valid date.';
  return errors;
}

/** Create/edit modal. `users` is only passed for admins, enabling the owner picker. */
export default function TaskForm({ task, users, currentUserId, onSave, onCancel }) {
  const [form, setForm] = useState({
    title: task?.title ?? '',
    description: task?.description ?? '',
    status: task?.status ?? 'todo',
    priority: task?.priority ?? 'medium',
    due_date: task?.due_date ?? '',
    user_id: task?.user_id ?? currentUserId,
  });
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);

  const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });

  const onSubmit = async (e) => {
    e.preventDefault();
    const clientErrors = validate(form);
    setErrors(clientErrors);
    setFormError('');
    if (Object.keys(clientErrors).length) return;

    const payload = {
      title: form.title.trim(),
      description: form.description.trim() || null,
      status: form.status,
      priority: form.priority,
      due_date: form.due_date || null,
    };
    if (users) payload.user_id = Number(form.user_id);

    setSaving(true);
    try {
      await onSave(payload);
    } catch (err) {
      setErrors(err.errors || {});
      setFormError(err.message);
      setSaving(false);
    }
  };

  return (
    <div className="modal-backdrop" onMouseDown={(e) => e.target === e.currentTarget && onCancel()}>
      <form className="modal" onSubmit={onSubmit} noValidate>
        <h2>{task ? 'Edit task' : 'New task'}</h2>

        <label>
          Title
          <input value={form.title} onChange={set('title')} maxLength={150} autoFocus />
          {errors.title && <span className="error">{errors.title}</span>}
        </label>

        <label>
          Description
          <textarea rows={3} value={form.description} onChange={set('description')} />
          {errors.description && <span className="error">{errors.description}</span>}
        </label>

        <div className="row">
          <label>
            Status
            <select value={form.status} onChange={set('status')}>
              {STATUSES.map((s) => <option key={s}>{s}</option>)}
            </select>
            {errors.status && <span className="error">{errors.status}</span>}
          </label>
          <label>
            Priority
            <select value={form.priority} onChange={set('priority')}>
              {PRIORITIES.map((p) => <option key={p}>{p}</option>)}
            </select>
            {errors.priority && <span className="error">{errors.priority}</span>}
          </label>
        </div>

        <div className="row">
          <label>
            Due date
            <input type="date" value={form.due_date} onChange={set('due_date')} />
            {errors.due_date && <span className="error">{errors.due_date}</span>}
          </label>
          {users && (
            <label>
              Owner
              <select value={form.user_id} onChange={set('user_id')}>
                {users.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.email})</option>)}
              </select>
              {errors.user_id && <span className="error">{errors.user_id}</span>}
            </label>
          )}
        </div>

        {formError && !Object.keys(errors).length && <p className="error" role="alert">{formError}</p>}

        <div className="actions">
          <button type="button" className="btn" onClick={onCancel} disabled={saving}>Cancel</button>
          <button className="btn primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button>
        </div>
      </form>
    </div>
  );
}
