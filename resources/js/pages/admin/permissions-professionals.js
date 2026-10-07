document.addEventListener('DOMContentLoaded', () => {
 document.querySelectorAll('[data-confirm]').forEach((form) => form.addEventListener('submit', (e) => {
  const message = form.getAttribute('data-confirm');
  if (message && !window.confirm(message)) e.preventDefault();
 }));
 document.querySelectorAll('[data-open-note]').forEach((button) => button.addEventListener('click', () => {
  const modal = document.getElementById(button.dataset.openNote); if (!modal) return;
  modal.hidden = false; modal.querySelector('textarea,select,input')?.focus();
 }));
 document.querySelectorAll('[data-close-modal]').forEach((button) => button.addEventListener('click', () => {
  const modal = button.closest('.admin-modal'); if (modal) modal.hidden = true;
 }));
 document.querySelectorAll('.admin-modal').forEach((modal) => modal.addEventListener('click', (e) => {
  if (e.target === modal) modal.hidden = true;
 }));
});
