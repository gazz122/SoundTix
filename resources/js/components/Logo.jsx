import logoSvg from '../../../assets/logo.svg?raw';

const ICON_PATH = logoSvg.match(/<path\s+d="([^"]+)"/)?.[1] ?? '';

export default function Logo({ dark = false, withText = true, iconClassName = 'h-9 w-9', textClassName = 'text-lg font-semibold', subText = null }) {
    return (
        <>
            <svg
                viewBox="0 0 1920 1920"
                role="img"
                aria-label="SoundTix"
                className={`shrink-0 ${iconClassName} ${dark ? 'text-white' : 'text-indigo-600'}`}
            >
                <g transform="translate(0,1920) scale(0.1,-0.1)">
                    <path d={ICON_PATH} fill="currentColor" />
                </g>
            </svg>
            {withText && (
                <span className={`leading-tight ${textClassName}`}>
                    <span className={dark ? 'text-white' : 'text-slate-900'}>Sound</span>
                    <span className={dark ? 'text-indigo-400' : 'text-indigo-600'}>Tix</span>
                    {subText && (
                        <span className={`mt-0.5 block text-xs ${dark ? 'text-white/70' : 'text-slate-500'}`}>{subText}</span>
                    )}
                </span>
            )}
        </>
    );
}