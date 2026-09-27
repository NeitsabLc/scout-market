import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['rich', 'editor', 'input', 'button'];

    connect() {
        this.richTarget.hidden = false;
        this.inputTarget.hidden = true;
        document.execCommand('defaultParagraphSeparator', false, 'p');
        this.sync();
    }

    format(event) {
        event.preventDefault();
        this.editorTarget.focus();
        document.execCommand(event.currentTarget.dataset.command, false);
        this.sync();
        this.updateToolbar();
    }

    sync() {
        const texte = this.editorTarget.textContent.replace(/\u00a0/g, ' ').trim();
        const contientUnRetour = this.editorTarget.querySelector('br');
        this.inputTarget.value = texte === '' && !contientUnRetour ? '' : this.editorTarget.innerHTML;
    }

    updateToolbar() {
        this.buttonTargets.forEach((button) => {
            button.setAttribute('aria-pressed', document.queryCommandState(button.dataset.command) ? 'true' : 'false');
        });
    }
}
