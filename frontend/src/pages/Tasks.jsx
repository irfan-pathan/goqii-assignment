import { useCallback, useEffect, useState } from 'react';
import { api } from '../api/client.js';
import { useAuth } from '../context/AuthContext.jsx';
import TaskForm, { STATUSES } from '../components/TaskForm.jsx';

const PER_PAGE = 10;

export default function Tasks() {
  const { user, isAdmin } = useAuth();
  const [tasks, setTasks] = useState([]);
  const [meta, setMeta] = useState({ page: 1, total: 0, total_pages: 1 });
  const [status, setStatus] = useState('');
  const [ownerId, setOwnerId] = useState('');
  const [page, setPage] = useState(1);
  const [users, setUsers] = useState([]); // admin only: owner filter + owner picker
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editing, setEditing] = useState(null); // null | 'new' | task
  const [busyId, setBusyId] = useState(null);
  const [reloadKey, setReloadKey] = useState(0);

  const reload = useCallback(() => setReloadKey((k) => k + 1), []);

  useEffect(() => {
    if (!isAdmin) return;
    api('/admin/users').then((res) => setUsers(res.data)).catch(() => {});
  }, [isAdmin]);

  useEffect(() => {
    let cancelled = false; // ignore stale responses when filters change quickly
    setLoading(true);
    setError('');
    const params = new URLSearchParams({ page, per_page: PER_PAGE });
    if (status) params.set('status', status);
    if (isAdmin && ownerId) params.set('user_id', ownerId);

    api(`/tasks?${params}`)
      .then((res) => {
        if (cancelled) return;
        setTasks(res.data);
        setMeta(res.meta);
        if (res.meta.page > res.meta.total_pages) setPage(res.meta.total_pages); // e.g. last item on page deleted
      })
      .catch((err) => !cancelled && setError(err.message))
      .finally(() => !cancelled && setLoading(false));
    return () => {
      cancelled = true;
    };
  }, [page, status, ownerId, isAdmin, reloadKey]);

  const save = async (payload) => {
    if (editing === 'new') {
      await api('/tasks', { method: 'POST', body: payload });
    } else {
      await api(`/tasks/${editing.id}`, { method: 'PATCH', body: payload });
    }
    setEditing(null);
    reload();
  };

  const changeStatus = async (task, next) => {
    setBusyId(task.id);
    setError('');
    try {
      const res = await api(`/tasks/${task.id}`, { method: 'PATCH', body: { status: next } });
      setTasks((list) => list.map((t) => (t.id === task.id ? res.data : t)));
      if (status && status !== next) reload(); // no longer matches the active filter
    } catch (err) {
      setError(err.message);
    } finally {
      setBusyId(null);
    }
  };

  const remove = async (task) => {
    if (!window.confirm(`Delete "${task.title}"?`)) return;
    setBusyId(task.id);
    setError('');
    try {
      await api(`/tasks/${task.id}`, { method: 'DELETE' });
      reload();
    } catch (err) {
      setError(err.message);
    } finally {
      setBusyId(null);
    }
  };

  const today = new Date().toISOString().slice(0, 10);

  return (
    <>
      <div className="toolbar">
        <h1>{isAdmin ? 'All tasks' : 'My tasks'}</h1>
        <span className="spacer" />
        <select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} aria-label="Filter by status">
          <option value="">All statuses</option>
          {STATUSES.map((s) => <option key={s}>{s}</option>)}
        </select>
        {isAdmin && (
          <select value={ownerId} onChange={(e) => { setOwnerId(e.target.value); setPage(1); }} aria-label="Filter by owner">
            <option value="">All users</option>
            {users.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
          </select>
        )}
        <button className="btn primary" onClick={() => setEditing('new')}>+ New task</button>
      </div>

      {error && <p className="error banner" role="alert">{error}</p>}
      {loading && <p className="muted">Loading tasks…</p>}
      {!loading && !error && tasks.length === 0 && (
        <p className="muted center">No tasks{status ? ` with status "${status}"` : ''} yet.</p>
      )}

      {tasks.length > 0 && (
        <div className="table-wrap" style={{ opacity: loading ? 0.5 : 1 }}>
          <table>
            <thead>
              <tr>
                <th>Title</th>
                {isAdmin && <th>Owner</th>}
                <th>Status</th>
                <th>Priority</th>
                <th>Due</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {tasks.map((t) => (
                <tr key={t.id}>
                  <td>
                    <strong>{t.title}</strong>
                    {t.description && <div className="muted small">{t.description}</div>}
                  </td>
                  {isAdmin && <td>{t.owner.name}{t.user_id === user.id && ' (you)'}</td>}
                  <td>
                    <select value={t.status} disabled={busyId === t.id}
                            onChange={(e) => changeStatus(t, e.target.value)} aria-label={`Status of ${t.title}`}>
                      {STATUSES.map((s) => <option key={s}>{s}</option>)}
                    </select>
                  </td>
                  <td><span className={`badge prio-${t.priority}`}>{t.priority}</span></td>
                  <td className={t.due_date && t.due_date < today && t.status !== 'done' ? 'overdue' : ''}>
                    {t.due_date ?? '—'}
                  </td>
                  <td className="actions">
                    <button className="btn small" onClick={() => setEditing(t)} disabled={busyId === t.id}>Edit</button>
                    <button className="btn small danger" onClick={() => remove(t)} disabled={busyId === t.id}>Delete</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {meta.total_pages > 1 && (
        <div className="pager">
          <button className="btn" disabled={page <= 1 || loading} onClick={() => setPage(page - 1)}>← Prev</button>
          <span>Page {meta.page} of {meta.total_pages} ({meta.total} tasks)</span>
          <button className="btn" disabled={page >= meta.total_pages || loading} onClick={() => setPage(page + 1)}>Next →</button>
        </div>
      )}

      {editing && (
        <TaskForm
          task={editing === 'new' ? null : editing}
          users={isAdmin ? users : null}
          currentUserId={user.id}
          onSave={save}
          onCancel={() => setEditing(null)}
        />
      )}
    </>
  );
}
