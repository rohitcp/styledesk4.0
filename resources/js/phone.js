/**
 * Phone country list and field initialisation.
 *
 * The prototype's SD.phoneField already builds the searchable combo — a search
 * box, a filtered listbox, keyboard navigation and per-country formatting — so
 * this does not reimplement any of that. It only does two things the prototype
 * left to each page: widen the country list, and actually initialise the field.
 *
 * SD.COUNTRIES is exported by reference and read at render time, so pushing to
 * it is enough; the prototype module itself stays unmodified, which is what
 * keeps it in step with the 37-page prototype.
 */

const EXTRA_COUNTRIES = [
    { iso: 'IE', name: 'Ireland', dial: '+353', flag: '🇮🇪', mask: '## ### ####' },
    { iso: 'NL', name: 'Netherlands', dial: '+31', flag: '🇳🇱', mask: '# ########' },
    { iso: 'BE', name: 'Belgium', dial: '+32', flag: '🇧🇪', mask: '### ## ## ##' },
    { iso: 'IT', name: 'Italy', dial: '+39', flag: '🇮🇹', mask: '### ### ####' },
    { iso: 'PT', name: 'Portugal', dial: '+351', flag: '🇵🇹', mask: '### ### ###' },
    { iso: 'CH', name: 'Switzerland', dial: '+41', flag: '🇨🇭', mask: '## ### ## ##' },
    { iso: 'AT', name: 'Austria', dial: '+43', flag: '🇦🇹', mask: '### ######' },
    { iso: 'SE', name: 'Sweden', dial: '+46', flag: '🇸🇪', mask: '## ### ## ##' },
    { iso: 'NO', name: 'Norway', dial: '+47', flag: '🇳🇴', mask: '### ## ###' },
    { iso: 'DK', name: 'Denmark', dial: '+45', flag: '🇩🇰', mask: '## ## ## ##' },
    { iso: 'FI', name: 'Finland', dial: '+358', flag: '🇫🇮', mask: '## ### ####' },
    { iso: 'PL', name: 'Poland', dial: '+48', flag: '🇵🇱', mask: '### ### ###' },
    { iso: 'CZ', name: 'Czechia', dial: '+420', flag: '🇨🇿', mask: '### ### ###' },
    { iso: 'GR', name: 'Greece', dial: '+30', flag: '🇬🇷', mask: '### ### ####' },
    { iso: 'NZ', name: 'New Zealand', dial: '+64', flag: '🇳🇿', mask: '## ### ####' },
    { iso: 'ZA', name: 'South Africa', dial: '+27', flag: '🇿🇦', mask: '## ### ####' },
    { iso: 'AE', name: 'United Arab Emirates', dial: '+971', flag: '🇦🇪', mask: '## ### ####' },
    { iso: 'SA', name: 'Saudi Arabia', dial: '+966', flag: '🇸🇦', mask: '## ### ####' },
    { iso: 'IN', name: 'India', dial: '+91', flag: '🇮🇳', mask: '##### #####' },
    { iso: 'SG', name: 'Singapore', dial: '+65', flag: '🇸🇬', mask: '#### ####' },
    { iso: 'HK', name: 'Hong Kong', dial: '+852', flag: '🇭🇰', mask: '#### ####' },
    { iso: 'MY', name: 'Malaysia', dial: '+60', flag: '🇲🇾', mask: '##-### ####' },
    { iso: 'JP', name: 'Japan', dial: '+81', flag: '🇯🇵', mask: '##-####-####' },
    { iso: 'BR', name: 'Brazil', dial: '+55', flag: '🇧🇷', mask: '## #####-####' },
    { iso: 'AR', name: 'Argentina', dial: '+54', flag: '🇦🇷', mask: '## ####-####' },
];

export function registerCountries() {
    if (!window.SD || !Array.isArray(window.SD.COUNTRIES)) {
        return;
    }

    const known = new Set(window.SD.COUNTRIES.map((c) => c.iso));

    EXTRA_COUNTRIES.forEach((country) => {
        if (!known.has(country.iso)) {
            window.SD.COUNTRIES.push(country);
        }
    });

    window.SD.COUNTRIES.sort((a, b) => a.name.localeCompare(b.name));
}

/**
 * Upgrade every [data-phone] container on the page.
 *
 * The selected country is written back into the hidden input beside the field
 * so it posts with the form: the widget keeps the dialling code in its own
 * state, and without this the server would only ever receive the national
 * number and could not tell +1 from +44.
 */
export function initPhoneFields(root = document) {
    if (!window.SD || typeof window.SD.phoneField !== 'function') {
        return;
    }

    registerCountries();

    root.querySelectorAll('[data-phone]').forEach((el) => {
        if (el.dataset.phoneReady) {
            return;
        }

        el.dataset.phoneReady = '1';

        const field = window.SD.phoneField(el);
        const hidden = el.querySelector('[data-phone-country-value]');

        if (!field || !hidden) {
            return;
        }

        const sync = () => {
            const value = field.value();

            if (value && value.iso) {
                hidden.value = value.iso;
            }
        };

        // The widget exposes no change event, so sync on the interactions that
        // can alter the country, plus once on submit as a backstop.
        el.addEventListener('click', sync);
        el.addEventListener('keyup', sync);
        el.closest('form')?.addEventListener('submit', sync);

        sync();
    });
}
