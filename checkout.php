<?php
require __DIR__ . '/config/app.php';

Auth::requireLogin(url('checkout.php'));
$user = Auth::user();
$cart = Cart::details();
if (!$cart['lines']) {
    flash('info', 'Sua sacola está vazia. Escolha uma peça para continuar.');
    redirect('catalogo.php');
}

$method = old('payment_method', 'pix');
$title = 'Finalizar compra';
$bodyClass = 'page-light';
$headerVariant = 'light';
$scripts = ['forms.js', 'checkout.js'];
require __DIR__ . '/views/partials/head.php';
require __DIR__ . '/views/partials/header.php';

$field = static function (string $name, string $label, array $attrs = [], string $default = '') {
    $error = field_error($name);
    $type = $attrs['type'] ?? 'text';
    unset($attrs['type']);
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' ' . $k . '="' . e($v) . '"';
    }
    $value = in_array($name, ['card_number', 'card_cvv'], true) ? '' : old($name, $default);
    echo '<div class="field' . ($error ? ' has-error' : '') . '">';
    echo '<label class="field__label" for="' . e($name) . '">' . e($label) . '</label>';
    echo '<input class="field__input" id="' . e($name) . '" type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . $extra . '>';
    if ($error) {
        echo '<p class="field__error">' . e($error) . '</p>';
    }
    echo '</div>';
};
?>
<main id="conteudo" class="checkout">
    <h1 class="page-title">Finalizar compra</h1>

    <?php foreach ($cart['notices'] as $notice): ?>
    <p class="notice" role="status"><?= e($notice) ?></p>
    <?php endforeach; ?>
    <?php if ($err = field_error('checkout')): ?>
    <p class="notice" role="alert"><?= e($err) ?></p>
    <?php endif; ?>

    <div class="checkout__layout">
        <form method="post" action="<?= e(url('actions/checkout.php')) ?>" novalidate data-validate data-checkout id="formCheckout">
            <?= Csrf::field() ?>

            <fieldset class="form-section">
                <legend>Entrega <span>01</span></legend>
                <div class="row g-3">
                    <div class="col-12"><?php $field('shipping_name', 'Quem recebe', ['required' => 'required', 'minlength' => '3', 'maxlength' => '120', 'autocomplete' => 'name'], $user['name']); ?></div>
                    <div class="col-sm-4"><?php $field('shipping_zip', 'CEP', ['required' => 'required', 'pattern' => '\d{5}-?\d{3}', 'inputmode' => 'numeric', 'maxlength' => '9', 'autocomplete' => 'postal-code', 'placeholder' => '00000-000', 'data-mask' => 'cep', 'data-msg' => 'CEP no formato 00000-000.']); ?></div>
                    <div class="col-sm-8"><?php $field('shipping_address', 'Endereço, número e complemento', ['required' => 'required', 'minlength' => '6', 'maxlength' => '255', 'autocomplete' => 'street-address']); ?></div>
                    <div class="col-sm-8"><?php $field('shipping_city', 'Cidade', ['required' => 'required', 'maxlength' => '100', 'autocomplete' => 'address-level2']); ?></div>
                    <div class="col-sm-4">
                        <div class="field<?= field_error('shipping_state') ? ' has-error' : '' ?>">
                            <label class="field__label" for="shipping_state">Estado</label>
                            <select class="field__select" id="shipping_state" name="shipping_state" required autocomplete="address-level1">
                                <option value="">UF</option>
                                <?php foreach (OrderService::STATES as $uf): ?>
                                <option value="<?= e($uf) ?>" <?= old('shipping_state') === $uf ? 'selected' : '' ?>><?= e($uf) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($err = field_error('shipping_state')): ?><p class="field__error"><?= e($err) ?></p><?php endif; ?>
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend>Pagamento <span>02</span></legend>
                <p class="sim-badge">Simulação: nenhuma cobrança real é feita</p>
                <div class="pay-options" role="radiogroup" aria-label="Forma de pagamento">
                    <?php foreach (['pix' => ['PIX', 'Aprovação imediata'], 'boleto' => ['Boleto', 'Compensação simulada na hora'], 'cartao' => ['Cartão de crédito', 'Até 10× sem juros']] as $value => [$name, $desc]): ?>
                    <label class="pay-option">
                        <input type="radio" name="payment_method" value="<?= e($value) ?>" <?= $method === $value ? 'checked' : '' ?> data-pay-option>
                        <span class="pay-option__box"><span class="pay-option__name"><?= e($name) ?></span><span class="pay-option__desc"><?= e($desc) ?></span></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php if ($err = field_error('payment_method')): ?><p class="field__error mb-3"><?= e($err) ?></p><?php endif; ?>

                <div class="pay-panel" data-pay-panel="pix" <?= $method === 'pix' ? '' : 'hidden' ?>>
                    <p>Ao confirmar, geramos o código PIX e o pagamento é aprovado na hora, nesta simulação.</p>
                </div>
                <div class="pay-panel" data-pay-panel="boleto" <?= $method === 'boleto' ? '' : 'hidden' ?>>
                    <p>O boleto é emitido e dado como compensado na hora, nesta simulação. Em uma loja real, o prazo é de até 2 dias úteis.</p>
                </div>
                <div class="pay-panel" data-pay-panel="cartao" <?= $method === 'cartao' ? '' : 'hidden' ?>>
                    <p class="mb-3">Use o cartão de teste <strong>4242 4242 4242 4242</strong>, qualquer validade futura e CVV de 3 dígitos. Não digite dados de um cartão real: só guardamos os 4 últimos dígitos.</p>
                    <div class="row g-3">
                        <div class="col-12"><?php $field('card_number', 'Número do cartão', ['inputmode' => 'numeric', 'autocomplete' => 'off', 'maxlength' => '23', 'pattern' => '[\d ]{13,23}', 'data-mask' => 'card', 'data-msg' => 'Número de cartão inválido.', 'data-pay-required' => '1']); ?></div>
                        <div class="col-12"><?php $field('card_name', 'Nome impresso no cartão', ['autocomplete' => 'off', 'maxlength' => '80', 'minlength' => '3', 'data-pay-required' => '1']); ?></div>
                        <div class="col-6"><?php $field('card_expiry', 'Validade', ['inputmode' => 'numeric', 'placeholder' => 'MM/AA', 'maxlength' => '5', 'pattern' => '(0[1-9]|1[0-2])\/\d{2}', 'data-mask' => 'expiry', 'autocomplete' => 'off', 'data-msg' => 'Use o formato MM/AA.', 'data-pay-required' => '1']); ?></div>
                        <div class="col-6"><?php $field('card_cvv', 'CVV', ['inputmode' => 'numeric', 'maxlength' => '4', 'pattern' => '\d{3,4}', 'autocomplete' => 'off', 'data-msg' => '3 ou 4 dígitos.', 'data-pay-required' => '1']); ?></div>
                    </div>
                </div>
            </fieldset>
        </form>

        <aside class="summary" aria-labelledby="resumoCheckout">
            <h2 class="summary__title" id="resumoCheckout">Seu pedido</h2>
            <ul class="summary__items">
                <?php foreach ($cart['lines'] as $line): $p = $line['product']; ?>
                <li>
                    <img src="<?= e(image_url(ProductRepository::thumb($p['image']))) ?>" alt="" width="56" height="70" loading="lazy">
                    <div><?= e($p['name']) ?><br><small class="text-secondary"><?= (int) $line['quantity'] ?> × <?= e(money($line['unit_price'])) ?></small></div>
                    <span><?= e(money($line['subtotal'])) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <div class="summary__row"><span>Subtotal</span><span><?= e(money($cart['total'])) ?></span></div>
            <div class="summary__row"><span>Entrega segurada</span><span>Grátis</span></div>
            <div class="summary__row summary__row--total"><span>Total</span><strong><?= e(money($cart['total'])) ?></strong></div>
            <button type="submit" form="formCheckout" class="btn-solid">Confirmar pedido</button>
            <a class="btn-line" href="<?= e(url('sacola.php')) ?>">Voltar à sacola</a>
            <p class="summary__note">Comprando como <?= e($user['email']) ?>. Estoque e preços são conferidos de novo no momento da confirmação.</p>
        </aside>
    </div>
</main>
<?php forget_form(); ?>
<?php require __DIR__ . '/views/partials/footer.php'; ?>
