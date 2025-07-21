// Função para animar os emoticons ao passar o mouse
$(document).ready(function() {
    $('.btn-emoji').hover(
        function() {
            $(this).css('transform', 'scale(1.2)');
        },
        function() {
            $(this).css('transform', 'scale(1)');
        }
    );
    
    // Marcar card do funcionário selecionado
    $('.funcionario-card input[type="radio"]').change(function() {
        $('.funcionario-card').removeClass('selected');
        $(this).closest('.funcionario-card').addClass('selected');
    });
    
    // Prevenir envio duplo de formulários
    $('form').submit(function() {
        $(this).find('button[type="submit"]').prop('disabled', true);
    });
});