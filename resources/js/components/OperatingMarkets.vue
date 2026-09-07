<script setup>
import { computed, ref, watch } from 'vue';
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
    /**
     * Country code => the language codes that country is served in. The same
     * map the validation reads, so the list the reader is shown and the list
     * the server accepts cannot drift apart.
     */
    countryLanguages: { type: Object, default: () => ({}) },
    selectedCountries: { type: Array, default: () => [] },
    selectedCurrencies: { type: Array, default: () => [] },
    selectedLanguages: { type: Array, default: () => [] },
    selectedLanguage: { type: String, default: 'en' },
});

/**
 * Empty until the business says otherwise.
 *
 * These used to arrive as the United States and the US dollar, which is a
 * guess wearing the clothes of an answer: a salon in Leeds that did not
 * notice the field had been filled in signed up priced in dollars. A required
 * field a reader has to answer is slower by one click and right every time.
 */
const countries = ref([...props.selectedCountries]);

/**
 * Primary and secondary are held apart, matching the fields.
 *
 * The stored list is one ordered set with the primary first; splitting it here
 * and re-joining on submit keeps that single source of truth while letting the
 * form say plainly which value is the default.
 */
const primaryCurrency = ref(props.selectedCurrencies[0] ? [props.selectedCurrencies[0]] : []);
const secondaryCurrencies = ref(props.selectedCurrencies.slice(1));

const primaryLanguage = ref([props.selectedLanguages[0] ?? props.selectedLanguage ?? 'en']);
const secondaryLanguages = ref(props.selectedLanguages.slice(1));

/**
 * The languages the chosen countries are served in.
 *
 * The union rather than the intersection: a business operating in Canada and
 * Mexico serves clients in English, French and Spanish, and the set those two
 * countries share is empty. Ordered by the language register, so the list
 * reads the same way whichever order the countries were ticked in.
 *
 * Falls back to every language while no country has been chosen — the field
 * above is required and is the thing to answer first, and an empty language
 * dropdown reads as broken rather than as waiting.
 */
const availableLanguages = computed(() => {
    if (!countries.value.length) {
        return props.languages;
    }

    const offered = new Set(
        countries.value.flatMap((code) => props.countryLanguages[code] ?? [])
    );

    return Object.fromEntries(
        Object.entries(props.languages).filter(([code]) => offered.has(code))
    );
});

// Promoting a value out of the secondaries must not leave it in both lists.
watch(primaryCurrency, ([code]) => {
    secondaryCurrencies.value = secondaryCurrencies.value.filter((c) => c !== code);
});

watch(primaryLanguage, ([code]) => {
    secondaryLanguages.value = secondaryLanguages.value.filter((c) => c !== code);
});

/**
 * Dropping a country drops the languages only it offered.
 *
 * Filtering the options alone is not enough: MultiSelect keeps its own copy of
 * the selection, so a language chosen while France was selected stays selected
 * and stays posted after France is removed — hidden from the list the reader
 * can see, and rejected by the validation that reads the same map. Pruning the
 * models here is what keeps the form unable to post an answer it is no longer
 * offering.
 *
 * The primary falls back to the first language still available rather than to
 * nothing, because it is required and a silently emptied required field is a
 * form that will not submit for no visible reason.
 */
watch(availableLanguages, (available) => {
    const offered = Object.keys(available);

    if (!offered.length) {
        return;
    }

    if (!offered.includes(primaryLanguage.value[0])) {
        primaryLanguage.value = [offered[0]];
    }

    const kept = secondaryLanguages.value.filter((code) => offered.includes(code));

    if (kept.length !== secondaryLanguages.value.length) {
        secondaryLanguages.value = kept;
    }
});

/**
 * Says why the list is as short as it is.
 *
 * Without this the field simply offers one language for a France-only
 * business, and a dropdown with a single entry reads as a bug rather than as
 * the answer to a question already asked further up the form.
 */
const languageHint = computed(() => {
    if (!countries.value.length) {
        return 'Used for the app, booking page, emails and receipts.';
    }

    return 'Used for the app, booking page, emails and receipts. Offered for the countries you operate in.';
});

// Whether the user has taken the currency list over. Once they have, the
// country stops rewriting it — a business may well price in something other
// than its own country's currency, and silently undoing that choice is worse
// than not suggesting at all.
const currencyTouched = ref(false);
/* Neutral until a country has actually suggested something. The field starts
   empty now, and "Suggested from your primary country" under an empty box
   describes a suggestion that has not happened. */
const hint = ref('The currency your services are priced in.');

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

                <MultiSelect v-model="primaryLanguage" :options="availableLanguages" name="default_language" single
                             aria-label="Primary language"
                             placeholder="Select a language"
                             search-placeholder="Search language…" />

                <p class="mt-1.5 text-[12px] text-sub">{{ languageHint }}</p>
            </div>

            <div>
                <label class="block text-[13px] font-medium text-ink mb-1.5">
                    Secondary languages <span class="font-normal text-faint ml-1">Optional</span>
                </label>

                <MultiSelect v-model="secondaryLanguages" :options="availableLanguages"
                             name="secondary_language_codes" :exclude="primaryLanguage" :show-primary="false"
                             aria-label="Secondary languages"
                             placeholder="Select languages…"
                             search-placeholder="Search language…" />

                <p class="mt-1.5 text-[12px] text-sub">Other languages your clients can be served in.</p>
            </div>
        </div>

    </div>
</template>
