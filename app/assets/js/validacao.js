/**
 * validacao.js - Validação client-side de formulários, anexo (PDF/PNG/JPG até 2MB)
 * e confirmação de exclusão de ações.
 */

document.addEventListener('DOMContentLoaded', () => {
    const TAMANHO_MAX_BYTES = 2 * 1024 * 1024; // 2 MB
    const EXTENSOES_VALIDAS = ['pdf', 'png', 'jpg', 'jpeg'];

    // 1. Validação em tempo real do input de arquivo (evidência)
    const inputAnexo = document.getElementById('anexo');
    const feedbackArquivo = document.getElementById('feedback-arquivo');

    if (inputAnexo) {
        inputAnexo.addEventListener('change', () => {
            const arquivo = inputAnexo.files[0];
            if (!arquivo) {
                return;
            }

            const partes = arquivo.name.split('.');
            const extensao = partes.length > 1 ? partes.pop().toLowerCase() : '';

            if (!EXTENSOES_VALIDAS.includes(extensao)) {
                alert('Formato inválido! Selecione apenas arquivos PDF, PNG ou JPG.');
                inputAnexo.value = '';
                return;
            }

            if (arquivo.size > TAMANHO_MAX_BYTES) {
                const tamanhoMb = (arquivo.size / (1024 * 1024)).toFixed(2);
                alert(`O arquivo selecionado (${tamanhoMb} MB) ultrapassa o limite máximo de 2 MB.`);
                inputAnexo.value = '';
                return;
            }

            if (feedbackArquivo) {
                const tamanhoKb = (arquivo.size / 1024).toFixed(1);
                feedbackArquivo.textContent = `Arquivo selecionado: ${arquivo.name} (${tamanhoKb} KB) — válido!`;
            }
        });
    }

    // 2. Validação antes do envio do formulário de Ação
    const formAcao = document.getElementById('form-acao');
    if (formAcao) {
        formAcao.addEventListener('submit', (evento) => {
            const titulo = document.getElementById('titulo')?.value.trim() || '';
            const descricao = document.getElementById('descricao')?.value.trim() || '';
            const responsavel = document.getElementById('responsavel')?.value.trim() || '';
            const prazo = document.getElementById('prazo')?.value.trim() || '';
            const idOferta = document.getElementById('id_oferta')?.value.trim() || '';

            if (!idOferta || !titulo || !descricao || !responsavel || !prazo) {
                evento.preventDefault();
                alert('Por favor, preencha todos os campos obrigatórios marcados com *.');
            }
        });
    }

    // 3. Confirmação antes de excluir uma Ação
    const formsExclusao = document.querySelectorAll('.js-confirmar-exclusao');
    formsExclusao.forEach((form) => {
        form.addEventListener('submit', (evento) => {
            const titulo = form.getAttribute('data-titulo') || 'esta ação';
            const confirmou = window.confirm(`Tem certeza que deseja excluir "${titulo}"? Esta operação não pode ser desfeita.`);
            if (!confirmou) {
                evento.preventDefault();
            }
        });
    });
});
