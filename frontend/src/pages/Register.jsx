import { useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';

// Mirrors the server-side rules in Validator::register (the server remains the source of truth).
function validate({ name, email, password }) {
  const errors = {};
  if (!name.trim() || name.trim().length > 100) errors.name = 'Name is required (max 100 characters).';
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) errors.email = 'A valid email is required.';
  if (password.length < 8 || password.length > 72) errors.password = 'Password must be 8-72 characters.';
  return errors;
}

export default function Register() {
  const { user, booting, register } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ name: '', email: '', password: '' });
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  if (!booting && user) return <Navigate to="/" replace />;

  const onSubmit = async (e) => {
    e.preventDefault();
    const clientErrors = validate(form);
    setErrors(clientErrors);
    setFormError('');
    if (Object.keys(clientErrors).length) return;

    setSubmitting(true);
    try {
      await register(form.name.trim(), form.email.trim(), form.password);
      navigate('/', { replace: true });
    } catch (err) {
      setErrors(err.errors || {});
      setFormError(err.message);
    } finally {
      setSubmitting(false);
    }
  };

  const field = (key, label, type = 'text', autoComplete) => (
    <label>
      {label}
      <input type={type} autoComplete={autoComplete} value={form[key]}
             onChange={(e) => setForm({ ...form, [key]: e.target.value })} />
      {errors[key] && <span className="error">{errors[key]}</span>}
    </label>
  );

  return (
    <div className="auth-card">
      <h1>Create account</h1>
      <form onSubmit={onSubmit} noValidate>
        {field('name', 'Name', 'text', 'name')}
        {field('email', 'Email', 'email', 'email')}
        {field('password', 'Password (min 8 characters)', 'password', 'new-password')}
        {formError && !Object.keys(errors).length && <p className="error" role="alert">{formError}</p>}
        <button className="btn primary" disabled={submitting}>
          {submitting ? 'Creating…' : 'Register'}
        </button>
      </form>
      <p className="muted">Already registered? <Link to="/login">Log in</Link></p>
    </div>
  );
}
