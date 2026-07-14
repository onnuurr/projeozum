<template>
    <div class="login-page">
        <BrandPanel />

        <main class="form-panel">
            <div class="form-top">
                <button
                    type="button"
                    class="lang-select"
                    aria-label="Dil seçin"
                >
                    <span class="lang-flag"></span>
                    Türkçe
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                        <path
                            d="M6 9l6 6 6-6"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </button>
            </div>

            <div v-if="step === 'credentials'" class="form-wrap">
                <div class="form-heading">
                    <h1>Tekrar hoş geldiniz</h1>
                    <p>Hesabınıza giriş yaparak üretim panelinize erişin.</p>
                </div>

                <form @submit.prevent="submitForm">
                    <FormField
                        label="E-posta adresi"
                        for-id="email"
                        :error="form.errors.email"
                    >
                        <FormInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            placeholder="ornek@firma.com"
                            autocomplete="email"
                            required
                        >
                            <template #icon>
                                <svg
                                    width="16"
                                    height="16"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                >
                                    <rect
                                        x="3"
                                        y="5"
                                        width="18"
                                        height="14"
                                        rx="2.5"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    />
                                    <path
                                        d="M3 7l9 6 9-6"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </template>
                        </FormInput>
                    </FormField>

                    <FormField
                        label="Parola"
                        for-id="password"
                        :error="form.errors.password"
                        :link="{ label: 'Parolamı unuttum', href: '#' }"
                    >
                        <FormInput
                            id="password"
                            v-model="form.password"
                            :type="passwordFieldType"
                            placeholder="En az 8 karakter"
                            autocomplete="current-password"
                            :minlength="8"
                            required
                        >
                            <template #icon>
                                <svg
                                    width="16"
                                    height="16"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                >
                                    <rect
                                        x="4"
                                        y="11"
                                        width="16"
                                        height="10"
                                        rx="2"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    />
                                    <path
                                        d="M8 11V8a4 4 0 118 0v3"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    />
                                </svg>
                            </template>
                            <template #trailing>
                                <button
                                    type="button"
                                    class="input-trail"
                                    @click="togglePasswordVisibility"
                                    aria-label="Parolayı göster"
                                >
                                    <svg
                                        width="16"
                                        height="16"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                    >
                                        <template
                                            v-if="
                                                passwordFieldType === 'password'
                                            "
                                        >
                                            <path
                                                d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            />
                                            <circle
                                                cx="12"
                                                cy="12"
                                                r="3"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            />
                                        </template>
                                        <template v-else>
                                            <path
                                                d="M3 3l18 18"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                                stroke-linecap="round"
                                            />
                                            <path
                                                d="M10.6 6.1A11 11 0 0112 6c6.5 0 10 7 10 7a18 18 0 01-3.1 4.1M6.1 6.1A18 18 0 002 12s3.5 7 10 7a11 11 0 005.4-1.4"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                                stroke-linecap="round"
                                            />
                                            <path
                                                d="M9.9 9.9a3 3 0 004.2 4.2"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            />
                                        </template>
                                    </svg>
                                </button>
                            </template>
                        </FormInput>
                    </FormField>

                    <div class="form-row">
                        <FormCheckbox
                            v-model="form.remember"
                            id="remember"
                            label="Beni hatırla"
                        />
                    </div>

                    <FormButton
                        type="submit"
                        variant="primary"
                        :loading="form.processing"
                    >
                        {{ form.processing ? "Giriş yapılıyor…" : "Giriş yap" }}
                    </FormButton>
                </form>

                <div class="divider">veya</div>

                <div class="social-row">
                    <SocialButton provider="google">Google</SocialButton>
                    <SocialButton provider="microsoft">Microsoft</SocialButton>
                </div>
            </div>

            <div v-else-if="step === 'otp'" class="form-wrap">
                <button type="button" class="otp-back" @click="cancelOtp">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                        <path
                            d="M19 12H5M12 19l-7-7 7-7"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                    Giriş ekranına dön
                </button>

                <div class="form-heading">
                    <h1>Doğrulama kodunu girin</h1>
                    <p>
                        6 haneli doğrulama kodu
                        <strong v-if="otp_email"> {{ otp_email }} </strong>
                        <span v-else>kayıtlı adresinize</span>
                        gönderildi.
                    </p>
                </div>

                <form @submit.prevent="submitOtp">
                    <FormField :error="otpForm.errors.otp">
                        <FormOtpInput
                            v-model="otpForm.otp"
                            :error="otpForm.errors.otp"
                            :disabled="otpForm.processing"
                            @complete="onOtpComplete"
                        />
                    </FormField>

                    <div class="otp-hint">
                        Kod gelmediyse
                        <button type="button" class="otp-resend" disabled>
                            tekrar gönder
                        </button>
                        <span class="otp-hint-note">(yakında)</span>
                    </div>

                    <FormButton
                        type="submit"
                        variant="primary"
                        :loading="otpForm.processing"
                        :disabled="(otpForm.otp || '').length < 6"
                    >
                        {{
                            otpForm.processing
                                ? "Doğrulanıyor…"
                                : "Doğrula ve giriş yap"
                        }}
                    </FormButton>
                </form>
            </div>

            <div class="form-bottom">
                <div>Hesabın yok mu? <a href="#">Demo talep et</a></div>
                <div class="legal">
                    <a href="#">Gizlilik</a>
                    <a href="#">Şartlar</a>
                </div>
            </div>
        </main>

        <ToastContainer :toasts="toasts" @dismiss="dismissToast" />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useForm, router } from "@inertiajs/vue3";
import ToastContainer from "@/Components/ToastContainer.vue";
import { useToast } from "@/composables/useToast.js";
import BrandPanel from "@/Components/Auth/BrandPanel.vue";
import FormField from "@/Components/Form/FormField.vue";
import FormInput from "@/Components/Form/FormInput.vue";
import FormCheckbox from "@/Components/Form/FormCheckbox.vue";
import FormButton from "@/Components/Form/FormButton.vue";
import FormOtpInput from "@/Components/Form/FormOtpInput.vue";
import SocialButton from "@/Components/Form/SocialButton.vue";

const props = defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: "" },
    requires_otp: { type: Boolean, default: false },
    otp_email: { type: String, default: "" },
});

const step = computed(() => (props.requires_otp ? "otp" : "credentials"));

const { toasts, showToast, dismissToast } = useToast();

watch(
    step,
    (newStep, oldStep) => {
        if (newStep === "otp" && oldStep !== "otp") {
            showToast({
                type: "info",
                title: "Doğrulama Gerekli",
                message: "SMS ile gelen 6 haneli kodu girin.",
            });
        }
    },
    { immediate: true },
);

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const otpForm = useForm({
    otp: "",
});

const passwordFieldType = ref("password");

const togglePasswordVisibility = () => {
    passwordFieldType.value =
        passwordFieldType.value === "password" ? "text" : "password";
};

const submitForm = () => {
    form.post(route("login"), {
        onSuccess: () => {
            form.reset("password");
        },
        onError: () => {
            form.reset("password");
            if (form.errors.email) {
                showToast({
                    type: "error",
                    title: "Giriş Hatası",
                    message: form.errors.email,
                });
            } else if (form.errors.password) {
                showToast({
                    type: "error",
                    title: "Giriş Hatası",
                    message: form.errors.password,
                });
            }
        },
    });
};

const submitOtp = () => {
    otpForm.post(route("login.otp"), {
        onSuccess: () => {
            showToast({
                type: "success",
                title: "Hoş geldiniz!",
                message: "Yönlendiriliyorsunuz…",
            });
        },
        onError: () => {
            otpForm.reset("otp");
            if (otpForm.errors.otp) {
                showToast({
                    type: "error",
                    title: "Doğrulama Hatası",
                    message: otpForm.errors.otp,
                });
            }
        },
    });
};

const onOtpComplete = () => {
    if (!otpForm.processing) submitOtp();
};

const cancelOtp = () => {
    router.delete(route("login.otp.cancel"), {
        onSuccess: () => {
            otpForm.reset();
            form.reset("password");
        },
    });
};
</script>

<style>
/* Scoped styles adapted from login.html */

#app > .login-page,
.login-page {
    position: fixed;
    inset: 0;
    z-index: 1;
    font-family:
        "Inter",
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Roboto,
        sans-serif;
    background: #d8d8e8;
    color: #1a1a2e;
    overflow: hidden;
    padding: 12px;
    display: grid;
    grid-template-columns: 1.05fr 1fr;
    gap: 8px;
    width: 100vw;
    height: 100vh;
}

/* ─────────────────────────── LEFT: Brand panel ─────────────────────────── */
.brand-panel {
    position: relative;
    background:
        radial-gradient(
            900px 600px at 80% 0%,
            rgb(var(--color-primary) / 0.55),
            transparent 60%
        ),
        radial-gradient(
            700px 500px at 0% 100%,
            rgb(var(--color-primary) / 0.55),
            transparent 60%
        ),
        linear-gradient(135deg, #1a1a2e 0%, #2a2a4e 55%, #312e81 100%);
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: clamp(20px, 3vw, 36px) clamp(20px, 3vw, 40px)
        clamp(24px, 3vw, 40px);
    color: #fff;
    min-width: 0;
}
.brand-panel::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image: radial-gradient(
        rgba(255, 255, 255, 0.05) 1px,
        transparent 1px
    );
    background-size: 22px 22px;
    background-position: 0 0;
    mask-image: linear-gradient(
        180deg,
        rgba(0, 0, 0, 0.6),
        rgba(0, 0, 0, 0.05)
    );
    pointer-events: none;
}

.brand-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    z-index: 2;
}
.brand-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 600;
    color: #fff;
    white-space: nowrap;
}
.brand-logo strong {
    font-weight: 800;
}
.brand-logo-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 8px;
    color: #fff;
    font-size: 15px;
}
.brand-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.75);
    text-decoration: none;
    padding: 7px 12px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition: all 0.15s;
}
.brand-back:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
}

.brand-hero {
    position: relative;
    z-index: 2;
    max-width: 480px;
    min-width: 0;
}
.brand-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: rgba(255, 255, 255, 0.72);
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    margin-bottom: 20px;
}
.brand-eyebrow .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #4ade80;
    box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.25);
}
.brand-headline {
    font-size: clamp(22px, 2.6vw, 38px);
    line-height: 1.12;
    font-weight: 700;
    letter-spacing: -0.02em;
    margin-bottom: 14px;
    overflow-wrap: break-word;
}
.brand-headline em {
    font-style: normal;
    background: linear-gradient(135deg, #c7d2fe 0%, rgb(var(--color-primary) / .5) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.brand-sub {
    font-size: clamp(12px, 1vw, 14px);
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.72);
    max-width: 420px;
}

/* Glass cards (decoration) */
.glass-stack {
    position: absolute;
    right: -40px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    flex-direction: column;
    gap: 14px;
    width: 320px;
    z-index: 1;
    pointer-events: none;
}
.glass-card {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 14px;
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    padding: 14px 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18);
}
.glass-card.gc-1 {
    transform: translateX(-30px) rotate(-3deg);
}
.glass-card.gc-2 {
    transform: translateX(20px) rotate(2deg);
}
.glass-card.gc-3 {
    transform: translateX(-10px) rotate(-1.5deg);
}
.gc-row {
    display: flex;
    align-items: center;
    gap: 12px;
}
.gc-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #fff;
}
.gc-icon.b1 {
    background: linear-gradient(135deg, rgb(var(--color-primary)), rgb(var(--color-primary)));
}
.gc-icon.b2 {
    background: linear-gradient(135deg, #22c55e, #14b8a6);
}
.gc-icon.b3 {
    background: linear-gradient(135deg, #f59e0b, #ef4444);
}
.gc-text {
    flex: 1;
    min-width: 0;
}
.gc-title {
    font-size: 12.5px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 2px;
}
.gc-meta {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.65);
}
.gc-value {
    font-size: 13px;
    font-weight: 700;
    color: #fff;
}
.gc-bar {
    margin-top: 10px;
    height: 4px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 999px;
    overflow: hidden;
}
.gc-bar > span {
    display: block;
    height: 100%;
    width: 72%;
    background: linear-gradient(90deg, #818cf8, rgb(var(--color-primary) / .5));
    border-radius: 999px;
}

/* Bottom features */
.brand-foot {
    position: relative;
    z-index: 2;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    max-width: 520px;
}
.feature {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 14px 16px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}
.feature-num {
    font-size: 18px;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.02em;
}
.feature-num span {
    color: rgb(var(--color-primary) / .5);
}
.feature-label {
    font-size: 11.5px;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.65);
}

/* ─────────────────────────── RIGHT: Form panel ─────────────────────────── */
.form-panel {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    border: 1px solid #ebebf0;
    display: flex;
    flex-direction: column;
    padding: 28px 36px;
    overflow-y: auto;
}

.form-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: auto;
}
.lang-select {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    font-weight: 500;
    color: #666;
    padding: 6px 10px;
    border-radius: 8px;
    border: 1px solid #ebebf0;
    background: #fff;
    cursor: pointer;
    transition: all 0.15s;
}
.lang-select:hover {
    background: #f5f5fb;
    color: #1a1a2e;
}
.lang-flag {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: linear-gradient(180deg, #e30a17 50%, #fff 50%);
    flex-shrink: 0;
}

.form-wrap {
    width: 100%;
    max-width: 380px;
    margin: 0 auto;
    padding: 32px 0;
}

.form-heading {
    margin-bottom: 28px;
}
.form-heading h1 {
    font-size: 26px;
    font-weight: 700;
    color: #1a1a2e;
    letter-spacing: -0.01em;
    margin-bottom: 6px;
}
.form-heading p {
    font-size: 13.5px;
    color: #666;
    line-height: 1.55;
}

/* Input field */
.field {
    margin-bottom: 14px;
}
.field-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12.5px;
    font-weight: 500;
    color: #555;
    margin-bottom: 6px;
}
.field-link {
    font-size: 12px;
    font-weight: 500;
    color: rgb(var(--color-primary));
    text-decoration: none;
    transition: color 0.15s;
}
.field-link:hover {
    color: rgb(var(--color-primary-hover));
}
.input-wrap {
    position: relative;
}
.input-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #9898b0;
    pointer-events: none;
    display: flex;
    align-items: center;
}
.input-trail {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: #9898b0;
    padding: 6px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    transition:
        color 0.15s,
        background 0.15s;
}
.input-trail:hover {
    color: #1a1a2e;
    background: #f5f5fb;
}
.form-input {
    height: 44px;
    width: 100%;
    padding: 0 14px 0 40px;
    border: 1.5px solid #e8e8f0;
    border-radius: 10px;
    font-family: inherit;
    font-size: 13.5px;
    color: #1a1a2e;
    background: #fafafe;
    outline: none;
    transition:
        border-color 0.15s,
        box-shadow 0.15s,
        background 0.15s;
}
.form-input.has-trail {
    padding-right: 42px;
}
.form-input:focus {
    border-color: rgb(var(--color-primary));
    box-shadow: 0 0 0 3px rgb(var(--color-primary) / 0.12);
    background: #fff;
}
.form-input::placeholder {
    color: #bbb;
}
.field.has-error .form-input {
    border-color: #ef4444;
    background: #fef2f2;
}
.field.has-error .form-input:focus {
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
}
.field-error {
    display: none; /* Controlled by Vue :class */
    margin-top: 6px;
    font-size: 12px;
    color: #991b1b;
    align-items: center;
    gap: 5px;
}
.field.has-error .field-error {
    display: flex;
}

/* OTP step */
.otp-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
    margin-bottom: 18px;
    font-family: inherit;
    font-size: 12.5px;
    font-weight: 500;
    color: #666;
    transition: color 0.15s;
}
.otp-back:hover {
    color: #1a1a2e;
}
.otp-hint {
    margin: 16px 0 18px;
    font-size: 12.5px;
    color: #666;
    text-align: center;
}
.otp-resend {
    background: none;
    border: none;
    padding: 0;
    font-family: inherit;
    font-size: 12.5px;
    font-weight: 600;
    color: rgb(var(--color-primary));
    cursor: pointer;
    transition: color 0.15s;
}
.otp-resend:hover:not(:disabled) {
    color: rgb(var(--color-primary-hover));
}
.otp-resend:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.otp-hint-note {
    color: #9898b0;
    font-size: 11.5px;
    margin-left: 4px;
}

/* Remember me */
.form-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 4px 0 18px;
}
.form-check {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    user-select: none;
    font-size: 12.5px;
    color: #555;
}
.form-check input {
    display: none;
}
.form-check-box {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    border: 1.5px solid #d0d0e0;
    background: #fafafe;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.15s;
}
.form-check input:checked + .form-check-box {
    background: #1a1a2e;
    border-color: #1a1a2e;
}
.form-check input:checked + .form-check-box::after {
    content: "";
    width: 4px;
    height: 7px;
    border: solid #fff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg) translateY(-1px);
}

/* Buttons */
.btn {
    height: 44px;
    width: 100%;
    border: none;
    border-radius: 10px;
    font-family: inherit;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-primary {
    background: #1a1a2e;
    color: #fff;
    box-shadow: 0 4px 14px rgba(26, 26, 46, 0.18);
}
.btn-primary:hover {
    background: #2a2a4e;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(26, 26, 46, 0.24);
}
.btn-primary:active {
    transform: translateY(0);
}
.btn-primary[disabled] {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}
.btn-secondary {
    background: #fff;
    color: #333;
    border: 1.5px solid #e8e8f0;
}
.btn-secondary:hover {
    background: #f5f5fb;
    border-color: #d8d8e0;
}

/* Spinner */
.spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    display: none;
}
.btn.is-loading .spinner {
    display: block;
}
.btn.is-loading .btn-label {
    opacity: 0.85;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Divider */
.divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 20px 0;
    font-size: 11.5px;
    font-weight: 600;
    color: #9898b0;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.divider::before,
.divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background: #ebebf0;
}

/* Social */
.social-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.btn-social {
    height: 42px;
    background: #fff;
    border: 1.5px solid #e8e8f0;
    border-radius: 10px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 500;
    color: #333;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.15s;
}
.btn-social:hover {
    background: #fafafe;
    border-color: #d8d8e0;
    transform: translateY(-1px);
}

/* Bottom signup */
.form-bottom {
    margin-top: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 20px;
    font-size: 12.5px;
    color: #666;
}
.form-bottom a {
    color: #1a1a2e;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.15s;
}
.form-bottom a:hover {
    color: rgb(var(--color-primary));
}
.form-bottom .legal {
    display: flex;
    gap: 14px;
    color: #9898b0;
    font-size: 11.5px;
}
.form-bottom .legal a {
    color: #9898b0;
    font-weight: 500;
}

/* Toast */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    width: 312px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 8px;
    pointer-events: none;
}
.toast {
    position: relative;
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e8e8f2;
    box-shadow:
        0 4px 16px rgba(0, 0, 0, 0.06),
        0 12px 36px rgba(0, 0, 0, 0.07);
    padding: 12px 14px 12px 18px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    transform: translateX(20px) scale(0.96);
    opacity: 0;
    transition:
        transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1),
        opacity 0.25s ease;
    pointer-events: auto;
    overflow: hidden;
}
.toast.show {
    transform: translateX(0) scale(1);
    opacity: 1;
}
.toast::after {
    content: "";
    position: absolute;
    top: 12px;
    bottom: 12px;
    left: 0;
    width: 3px;
    border-radius: 0 3px 3px 0;
    background: rgb(var(--color-primary));
}
.toast.error::after {
    background: #ef4444;
}
.toast-icon {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: rgb(var(--color-primary-soft));
    color: rgb(var(--color-primary));
}
.toast.error .toast-icon {
    background: #fef2f2;
    color: #ef4444;
}
.toast-body {
    flex: 1;
    min-width: 0;
}
.toast-title {
    font-size: 13px;
    font-weight: 600;
    color: #1a1a2e;
    margin-bottom: 2px;
}
.toast-msg {
    font-size: 12px;
    color: #666;
    line-height: 1.45;
}

/* Responsive */
@media (max-width: 1380px) {
    .glass-stack {
        display: none;
    }
}
@media (max-width: 1024px) {
    #app > .login-page,
    .login-page {
        grid-template-columns: 1fr;
    }
    .brand-panel {
        display: none;
    }
}
@media (max-width: 540px) {
    #app > .login-page,
    .login-page {
        padding: 0;
        gap: 0;
    }
    .form-panel {
        border-radius: 0;
        box-shadow: none;
        border: none;
        padding: 20px;
    }
    .toast-container {
        width: min(312px, calc(100vw - 16px));
        right: 8px;
    }
}

/* Scrollbar */
::-webkit-scrollbar {
    width: 4px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.12);
    border-radius: 4px;
}
</style>
