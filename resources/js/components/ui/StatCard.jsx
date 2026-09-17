export default function StatCard({ label, value, hint }) {
    return (
        <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-5">
            <p className="text-sm font-medium text-slate-500">{label}</p>
            <p className="mt-2 whitespace-nowrap text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">{value}</p>
            {hint && <p className="mt-1 text-xs text-slate-400">{hint}</p>}
        </div>
    );
}