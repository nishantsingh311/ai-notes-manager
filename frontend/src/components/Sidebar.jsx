import { BookOpen, FileText, Sparkles } from "lucide-react";

export default function Sidebar() {
    return <aside className="sidebar"><div className="brand"><div className="brand-icon"><BookOpen size={22} /></div><span>NoteSpace</span></div><div className="sidebar-label">WORKSPACE</div><div className="sidebar-link active"><FileText size={18} /><span>All Notes</span></div><div className="sidebar-bottom"><div className="ai-label"><Sparkles size={17} /><span>AI-powered notes</span></div><p>Organise your thoughts. Find ideas faster.</p></div></aside>;
}
