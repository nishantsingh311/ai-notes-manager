import { BookOpen, FileText, Sparkles } from "lucide-react";

const cards = [
    { key: "total_notes", label: "Total Notes", caption: "All saved notes", icon: FileText, color: "purple" },
    { key: "ai_summaries", label: "AI Summaries", caption: "Summaries generated", icon: Sparkles, color: "green" },
    { key: "recent_notes", label: "Recent Notes", caption: "Created in last 7 days", icon: BookOpen, color: "orange" },
];

export default function StatsCards({ stats }) {
    return <section className="stats-grid">{cards.map(({ key, label, caption, icon: Icon, color }) => <div className="stat-card" key={key}><div className={`stat-icon ${color}`}><Icon size={21} /></div><div><p>{label}</p><h2>{stats[key]}</h2><span>{caption}</span></div></div>)}</section>;
}
