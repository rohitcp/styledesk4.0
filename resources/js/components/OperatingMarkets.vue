<script setup>
import { ref, watch } from 'vue';
import MultiSelect from './MultiSelect.vue';

/**
 * Countries and currencies of operation, plus the default language.
 *
 * One island owns all three because the currency suggestion depends on the
 * primary country. Split across separate islands they would be separate Vue
 * apps on separate subtrees, and the suggestion would need an event bus to
 * cross between them for no benefit.
 *
 * Both lists carry a primary — the first entry. The primary country drives
 * every country-specific option on the later steps; the primary currency is
 * what services are priced in.
 */
const props = defineProps({
    countries: { type: Object, default: () => ({}) },
    currencies: { type: Object, default: () => ({}) },
    countryCurrencies: { type: Object, default: () => ({}) },
    languages: { type: Object, default: () => ({}) },
    selectedCountries: { type: Array, default: () => [] },
    selectedCurrencies: { type: Array, default: () => [] },
    selectedLanguages: { type: Array, default: () => [] },
    selectedLanguage: { type: String, default: 'en' },
});

const countries = ref(props.selectedCountries.length ? [...props.selectedCountries] : ['US']);
/**
 * Primary and secondary are held apart, matching the fields.
 *
 * The stored list is one ordered set with the primary first; splitting it here
 * and re-joining on submit keeps that single source of truth while letting the
 * form say plainly which value is the default.
 */
const primaryCurrency = ref([props.selectedCurrencies[0] ?? 'USD']);
const secondaryCurrencies = ref(props.selectedCurrencies.slice(1));

const primaryLanguage = ref([props.selectedLanguages[0] ?? props.selectedLanguage ?? 'en']);
const secondaryLanguages = ref(props.selectedLanguages.slice(1));

// Promoting a value out of the secondaries must not leave it in both lists.
watch(primaryCurrency, ([code]) => {
    secondaryCurrencies.value = secondaryCurrencies.value.filter((c) => c !== code);
});

watch(primaryLanguage, ([code]) => {
    secondaryLanguages.value = secondaryLanguages.value.filter((c) => c !== code);
});

// Whether the user has taken the currency list over. Once they have, the
// country stops rewriting it — a business may well price in something other
// than its own country's currency, and silently undoing that choice is worse
// than not suggesting at all.
const currencyTouched = ref(false);
const hint = ref('Suggested from your primary country — change it if you price differently.');

watch(primaryCurrency, () => { currencyTouched.value = true; }, { deep: true });

function onPrimaryCountry(code) {
    // Announced for anything outside Vue that follows the country — today the
    // phone widget's dialling code.
    document.dispatchEvent(new CustomEvent('styledesk:primary-country', { detail: { country: code } }));

    const suggested = props.countryCurrencies[code];

    if (!suggested || currencyTouched.value || primaryCurrency.value[0] === suggested) {
        return;
    }

    // The old primary is kept as a secondary rather than discarded: a business
    // operating in two countries usually trades in both.
    const previous = primaryCurrency.value[0];

    primaryCurrency.value = [suggested];

    if (previous && previous !== suggested && !secondaryCurrencies.value.includes(previous)) {
        secondaryCurrencies.value = [previous, ...secondaryCurrencies.value];
    }

    currencyTouched.value = false;
    hint.value = 'Set from your primary country — change it if you price differently.';
}
</script>

<template>
    <div class="space-y-5">
        <div>
            <label class="block text-[13px] font-medium text-ink mb-1.5">
                Country of operation <span class="text-danger" aria-hidden="true">*</span>
                <span class="font-normal text-faint ml-1">Select one or more</span>
            </label>

            <MultiSelect v-model="countries" :options="props.countries" name="country_codes"
                         aria-label="Countries of operation"
                         placeholder="Search or select countries"
                         search-placeholder="Search country…"
                         hint="The primary country sets your currency, regions, phone codes and timezones."
                         @primary-changed="onPrimaryCountry" />

            <p class="mt-1.5 text-[12px] text-sub">
                The first country is your primary. It sets the regions, phone codes and timezones offered later.
            </p>
        </div>

        <!-- Each on its own row: the chips wrap as more are selected, and a
             half-width field turns three selections into three stacked lines. -->
        <div class="grid sm:grid-cols-2 gap-x-5 gap-y-5">
            <div>
                <label class="block text-[13px] font-medium text-ink mb-1.5">
                    Primary currency <span class="text-danger" aria-hidden="true">*</span>
                </label>

                <MultiSelect v-model="primaryCurrency" :options="props.currencies" name="currency_code" single
                             aria-label="Primary currency"
                             placeholder="Select a currency"
                             search-placeholder="Search currency…" />

                <p class="mt-1.5 text-[12px] text-sub">{{ hint }}</p>
            </div>

            <div>
                <label class="block text-[13px] font-medium text-ink mb-1.5">
                    Secondary currencies <span class="font-normal text-faint ml-1">Optional</span>
                </label>

                <MultiSelect v-model="secondaryCurrencies" :options="props.currencies"
                             name="secondary_currency_codes" :exclude="primaryCurrency" :show-primary="false"
                             aria-label="Secondary currencies"
                             placeholder="Select currencies…"
                             search-placeholder="Search currency…" />

                <p class="mt-1.5 text-[12px] text-sub">Additional currencies you price services in.</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-x-5 gap-y-5">
            <div>
                <label class="block text-[13px] font-medium text-ink mb-1.5">
                    Primary language <span class="text-danger" aria-hidden="true">*</span>
                </label>

                <MultiSelect v-model="primaryLanguage" :options="languages" name="default_language" single
                             aria-label="Primary language"
                             placeholder="Select a language"
                             search-placeholder="Search language…" />

                <p class="mt-1.5 text-[12px] text-sub">Used for the app, booking page, emails and receipts.</p>
            </div>

            <div>
                <label class="block text-[13px] font-medium text-ink mb-1.5">
                    Secondary languages <span class="font-normal text-faint ml-1">Optional</span>
                </label>

                <MultiSelect v-model="secondaryLanguages" :options="languages"
                             name="secondary_language_codes" :exclude="primaryLanguage" :show-primary="false"
                             aria-label="Secondary languages"
                             placeholder="Select languages…"
                             search-placeholder="Search language…" />

                <p class="mt-1.5 text-[12px] text-sub">Other languages your clients can be served in.</p>
            </div>
        </div>

    </div>
</template>
