document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-profile-update]');
    const status = document.getElementById('profile-save-status');
    if (!form || !status) return;
    let revision = 0;
    let submittedRevision = 0;
    const bar = status.closest('.save-bar');
    form.addEventListener('change', () => {
        revision++;
        status.textContent = status.dataset.unsaved;
        bar.classList.add('is-dirty');
    });
    form.addEventListener('submit', () => { submittedRevision = revision; });
    form.addEventListener('ajax-crud:success', () => {
        const changedDuringSave = revision !== submittedRevision;
        status.textContent = changedDuringSave ? status.dataset.unsaved : status.dataset.saved;
        bar.classList.toggle('is-dirty', changedDuringSave);
    });
    form.addEventListener('ajax-crud:error', () => {
        status.textContent = status.dataset.failed;
        bar.classList.add('is-dirty');
    });
});
