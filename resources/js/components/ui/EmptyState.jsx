export default function EmptyState({ title, description, action = null, compact = false }) {
    return (
        <div className={`flex flex-col items-center justify-center px-6 text-center ${compact ? 'py-10' : 'py-16'}`}>
            <svg
                aria-hidden="true"
                className="h-16 w-16 text-slate-300"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.2"
                viewBox="0 0 24 24"
            >
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z"
                />
            </svg>
            <h3 className="mt-5 text-lg font-semibold text-slate-900">{title}</h3>
            {description && <p className="mt-2 max-w-sm text-sm text-slate-500">{description}</p>}
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}