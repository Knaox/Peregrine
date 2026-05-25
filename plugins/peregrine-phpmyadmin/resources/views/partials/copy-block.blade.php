{{-- Reusable copy-to-clipboard code block. Expects: $code (string), $filename (string label). --}}
<div
    x-data="{
        copied: false,
        labelCopy: @js(__('peregrine-phpmyadmin::messages.settings.copy')),
        labelCopied: @js(__('peregrine-phpmyadmin::messages.settings.copied')),
        markCopied() {
            this.copied = true;
            setTimeout(() => { this.copied = false; }, 2000);
        },
        copy() {
            // navigator.clipboard only exists in a secure context (HTTPS or
            // localhost). Over plain HTTP (LAN testing) it's undefined, so fall
            // back to selecting the code + execCommand.
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(this.$refs.code.innerText)
                    .then(() => this.markCopied())
                    .catch(() => this.selectAndCopy());
                return;
            }
            this.selectAndCopy();
        },
        selectAndCopy() {
            // Select the code element itself — it lives inside the modal, so the
            // selection survives Filament's focus trap (an off-DOM textarea does
            // not). execCommand('copy') still works over plain HTTP.
            const range = document.createRange();
            range.selectNodeContents(this.$refs.code);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
            let ok = false;
            try { ok = document.execCommand('copy'); } catch (e) {}
            if (ok) {
                sel.removeAllRanges();
                this.markCopied();
            }
            // If it still failed, the text stays selected so the user can press Ctrl/Cmd+C.
        },
    }"
    style="margin:0.6rem 0;border:1px solid rgba(148,163,184,0.25);border-radius:0.6rem;overflow:hidden;background:#0f172a;"
>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;padding:0.45rem 0.7rem;background:rgba(148,163,184,0.12);border-bottom:1px solid rgba(148,163,184,0.18);">
        <span style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:0.7rem;color:#cbd5e1;">{{ $filename }}</span>
        <button
            type="button"
            x-on:click="copy()"
            x-bind:aria-label="copied ? labelCopied : labelCopy"
            style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.28rem 0.6rem;border-radius:0.4rem;border:1px solid rgba(148,163,184,0.3);background:rgba(15,23,42,0.55);color:#e2e8f0;font-size:0.7rem;font-weight:600;cursor:pointer;transition:border-color .15s ease,color .15s ease;"
            x-bind:style="copied ? 'border-color:#22c55e;color:#22c55e;' : ''"
        >
            <svg x-show="!copied" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            <svg x-show="copied" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <span x-text="copied ? labelCopied : labelCopy"></span>
        </button>
    </div>
    <pre style="margin:0;padding:0.85rem 0.95rem;overflow:auto;max-height:46vh;"><code x-ref="code" style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:0.74rem;line-height:1.55;color:#e2e8f0;white-space:pre;">{{ $code }}</code></pre>
</div>
