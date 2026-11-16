import { LoaderCircle, Sparkles, X } from "lucide-react";

export default function SummaryModal({ summary, loading, onClose }) {
    return <div className="modal-overlay" onMouseDown={onClose}><div className="modal summary-modal" onMouseDown={(event) => event.stopPropagation()}><div className="modal-header"><div><div className="summary-title"><Sparkles size={20} /><h2>AI Summary</h2></div><p>A concise overview of your note.</p></div><button className="close-button" onClick={onClose} aria-label="Close"><X size={20} /></button></div><div className="summary-content">{loading ? <div className="loading-state"><LoaderCircle className="spin" size={24} /><span>Generating summary...</span></div> : summary}</div></div></div>;
}
