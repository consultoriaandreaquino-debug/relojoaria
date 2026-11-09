<?php
require __DIR__ . '/config/app.php';

$title = 'Atendimento e políticas';
$bodyClass = 'page-light';
$headerVariant = 'light';
require __DIR__ . '/views/partials/head.php';
require __DIR__ . '/views/partials/header.php';
?>
<main id="conteudo" class="info-page">
    <nav aria-label="Nesta página">
        <ul class="info-page__nav">
            <li><a href="#contato">Fale com a boutique</a></li>
            <li><a href="#entrega">Entrega e prazos</a></li>
            <li><a href="#garantia">Garantia e revisão</a></li>
            <li><a href="#trocas">Trocas e devoluções</a></li>
            <li><a href="#privacidade">Privacidade</a></li>
            <li><a href="#termos">Termos de uso</a></li>
        </ul>
    </nav>
    <div>
        <p class="eyebrow">Atendimento</p>
        <h1 class="page-title">Estamos por perto</h1>

        <section id="contato">
            <h2>Fale com a boutique</h2>
            <p>Telefone e WhatsApp: (11) 3000-1968, de segunda a sábado, das 10h às 19h.</p>
            <p>E-mail: atendimento@morvane.com.br. Respondemos em até um dia útil.</p>
            <p>Boutique e oficina: Rua Oscar Freire, São Paulo. Visitas com hora marcada.</p>
        </section>

        <section id="entrega">
            <h2>Entrega e prazos</h2>
            <p>Todas as entregas são seguradas e gratuitas para o Brasil. A peça sai da oficina em até 2 dias úteis depois da confirmação do pagamento.</p>
            <p>Prazo de transporte: 1 a 3 dias úteis para capitais do Sudeste e Sul, 3 a 7 dias úteis para as demais regiões. A entrega exige assinatura de quem recebe.</p>
        </section>

        <section id="garantia">
            <h2>Garantia e revisão</h2>
            <p>Cada relógio tem 5 anos de garantia contra defeitos de fabricação. Recomendamos uma revisão completa a cada 5 a 7 anos de uso.</p>
            <p>A oficina revisa qualquer Morvane, de qualquer época, mesmo fora da garantia. O orçamento é enviado antes de qualquer serviço.</p>
        </section>

        <section id="trocas">
            <h2>Trocas e devoluções</h2>
            <p>Você tem 7 dias corridos a partir do recebimento para desistir da compra, como prevê o Código de Defesa do Consumidor. A peça precisa voltar sem sinais de uso, com película, estojo e certificado.</p>
            <p>Peças com gravação personalizada não podem ser trocadas, exceto por defeito.</p>
        </section>

        <section id="privacidade">
            <h2>Privacidade</h2>
            <p>Guardamos só o necessário para entregar seus pedidos: nome, e-mail, endereço de entrega e o histórico de compras. Sua senha é armazenada com criptografia de mão única e ninguém da equipe tem acesso a ela.</p>
            <p>Não guardamos número de cartão. Neste site de demonstração, o pagamento é simulado e só os 4 últimos dígitos do cartão de teste ficam registrados no pedido.</p>
        </section>

        <section id="termos">
            <h2>Termos de uso</h2>
            <p>Este site é um projeto acadêmico. A Morvane é uma marca fictícia, os produtos não são vendidos de verdade e nenhum pagamento é cobrado.</p>
        </section>
    </div>
</main>
<?php require __DIR__ . '/views/partials/footer.php'; ?>
