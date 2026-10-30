import { useEffect, useState } from 'react';
import { api } from '../api/client.js';

export default function AdminDashboard() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    api('/admin/stats')
      .then((res) => setRows(res.data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  return (
    <>
      <h1>Task counts per user</h1>
      {error && <p className="error banner" role="alert">{error}</p>}
      {loading && <p className="muted">Loading…</p>}
      {!loading && !error && (
        <div className="table-wrap">
          <table>
            <thead>
              <tr><th>User</th><th>Email</th><th>Total</th><th>To do</th><th>In progress</th><th>Done</th></tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id}>
                  <td>{r.name}</td><td>{r.email}</td>
                  <td><strong>{r.total}</strong></td><td>{r.todo}</td><td>{r.in_progress}</td><td>{r.done}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
