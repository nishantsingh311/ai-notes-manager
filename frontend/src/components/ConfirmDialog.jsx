export default function ConfirmDialog({ open, title = "Delete note?", message = "Are you sure you want to delete this note?", onCancel, onConfirm }) {
    if (!open) return null;
    return <div className="modal-overlay" onMouseDown={onCancel}><div className="modal confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" onMouseDown={(event) => event.stopPropagation()}><div className="modal-header"><div><h2 id="confirm-title">{title}</h2><p>{message}</p></div></div><div className="modal-footer"><button className="secondary-button" onClick={onCancel}>Cancel</button><button className="primary-button" onClick={onConfirm}>Delete Note</button></div></div></div>;
}
