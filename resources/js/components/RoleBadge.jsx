const ROLE_META = {
    user: { label: 'User', className: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
    organizer: { label: 'Vendor', className: 'bg-indigo-600 text-white border-indigo-600' },
    gatekeeper: { label: 'Gatekeeper', className: 'bg-amber-50 text-amber-700 border-amber-200' },
    admin: { label: 'Admin', className: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
};

export function RoleBadge({ role }) {
    const meta = ROLE_META[role] ?? ROLE_META.user;

    return (
        <span className={`mt-1 inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium ${meta.className}`}>
            {meta.label}
        </span>
    );
}