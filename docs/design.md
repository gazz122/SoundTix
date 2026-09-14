# SoundTix Design System Guidelines

**Core Visual Concept:**
* Clean, Modern, Soft Light Mode, Natural Human-Centric UI.
* Dominant Palette: Off-White `#F8FAFC` (Main BG), Pure White `#FFFFFF` (Surface/Card), Slate `#1E293B` (Text), Muted Indigo `#4F46E5` (Primary Accent).
* Rule: Dilarang keras menggunakan gaya UI "AI-style" (seperti gradasi neon, glow effect, dark futuristic, atau warna-warni berlebihan). Utamakan kesederhanaan, kerapian, dan ruang kosong (whitespace) yang lega.

**UI/UX Component Standards (Tailwind CSS):**
1. **Layout & Background:**
   * Main Background: `bg-slate-50 text-slate-800 min-h-screen font-sans`.
   * Container: `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8`.

2. **Cards & Surface:**
   * Card Component: `bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm hover:shadow-md hover:border-slate-300 transition-all duration-200`.

3. **Buttons & Action Items:**
   * Primary Button: `bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm`.
   * Secondary Button: `bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 py-2.5 px-5 rounded-lg transition-all`.

4. **Forms & Inputs:**
   * Input Field: `bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all`.

5. **Status & Badges:**
   * Status Tag: `bg-slate-100 text-slate-600 border border-slate-200 text-xs px-2.5 py-1 rounded-md font-medium`.

6. **UX Principles:**
   * Tampilan terasa ringan, mengutamakan fungsionalitas dan kenyamanan membaca.'