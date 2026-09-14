import { Button } from '@/components/ui/button';
import { useTheme } from '@/hooks/useTheme';
import { useTranslations } from '@/lib/i18n';
import { Moon, Sun } from 'lucide-react';

export function ThemeToggle({ className }: { className?: string }) {
    const { theme, toggle } = useTheme();
    const { t } = useTranslations();
    const label = theme === 'dark' ? t('theme.switch_to_light') : t('theme.switch_to_dark');

    return (
        <Button variant="ghost" size="icon-sm" onClick={toggle} aria-label={label} title={label} className={className}>
            {theme === 'dark' ? <Moon aria-hidden="true" strokeWidth={1.75} /> : <Sun aria-hidden="true" strokeWidth={1.75} />}
        </Button>
    );
}
