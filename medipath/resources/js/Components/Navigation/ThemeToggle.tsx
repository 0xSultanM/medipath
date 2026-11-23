/**
 * ThemeToggle — COMPONENT-SPEC §0 (theme mechanism) + G7.
 * Toggles the explicit mode; persistence goes through ThemeProvider →
 * user_preferences (authenticated) or local preference (guest).
 */
import { useTheme } from '@/providers/ThemeProvider';

export function ThemeToggle({ compact = false }: { compact?: boolean }) {
    const { theme, toggle } = useTheme();

    return (
        <label className="switch" title={theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'}>
            <input
                type="checkbox"
                checked={theme === 'dark'}
                onChange={toggle}
                aria-label="Dark theme"
            />
            {!compact ? (
                <span className="caption" aria-hidden="true">
                    {theme === 'dark' ? 'Dark' : 'Light'}
                </span>
            ) : null}
        </label>
    );
}