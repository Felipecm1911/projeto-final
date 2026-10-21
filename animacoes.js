// Animações com GSAP. Se o GSAP não carregar, nada fica escondido: as telas só aparecem sem movimento.
(function () {
    if (!window.gsap) return;
    gsap.config({ nullTargetWarn: false }); // nem toda tela tem gráfico, aviso ou tabela

    var brl = function (n) { return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

    // Faz um número subir até o valor (usado no painel e no total do pedido)
    window.animarValor = function (el, valor, prefixo) {
        var atual = { v: parseFloat(el.dataset.atual || 0) };
        el.dataset.atual = valor;
        gsap.to(atual, {
            v: valor, duration: .6, ease: 'power2.out',
            onUpdate: function () { el.textContent = (prefixo || '') + brl(atual.v); }
        });
    };

    var mm = gsap.matchMedia();
    mm.add('(prefers-reduced-motion: no-preference)', function () {
        var ease = 'power3.out';

        // Login: o texto sobe em sequência (o zoom das fotos fica no carrossel)
        if (document.querySelector('.login')) {
            gsap.from('.login-lado > *, .login-lado form > *', { y: 24, opacity: 0, duration: .6, stagger: .06, ease: ease, delay: .1 });
            return;
        }

        // Entrada da tela: cabeçalho, depois cada bloco
        var blocos = gsap.utils.toArray('.principal > *');
        gsap.from(blocos, { y: 24, opacity: 0, duration: .6, stagger: .08, ease: ease });

        // Linhas das tabelas (só as primeiras, para não atrasar listas longas)
        gsap.utils.toArray('table').forEach(function (tabela) {
            var linhas = Array.prototype.slice.call(tabela.querySelectorAll('tbody tr'), 0, 12);
            gsap.from(linhas, { y: 10, opacity: 0, duration: .4, stagger: .03, ease: ease, delay: .25 });
        });

        // Barras do gráfico crescem uma por vez
        gsap.from('.barra-dia i', { scaleY: 0, transformOrigin: 'bottom', duration: .8, stagger: .07, ease: 'power2.out', delay: .3 });
        gsap.from('.barra-dia b', { opacity: 0, y: 6, duration: .3, stagger: .07, delay: .7 });

        // Números contam a partir do zero
        gsap.utils.toArray('[data-contar]').forEach(function (el) {
            var fim = parseFloat(el.dataset.contar), moeda = el.dataset.moeda !== undefined, obj = { v: 0 };
            gsap.to(obj, {
                v: fim, duration: 1.2, ease: 'power2.out', delay: .2,
                onUpdate: function () { el.textContent = (moeda ? (el.dataset.moeda || '') : '') + (moeda ? brl(obj.v) : Math.round(obj.v)); }
            });
        });

        // Avisos piscam suavemente ao aparecer
        gsap.from('.mensagem', { y: -12, opacity: 0, duration: .5, ease: ease });
    });

    // Retorno ao toque: botões afundam um pouco
    document.querySelectorAll('.botao').forEach(function (b) {
        b.addEventListener('pointerdown', function () { gsap.to(b, { scale: .96, duration: .1 }); });
        ['pointerup', 'pointerleave'].forEach(function (ev) {
            b.addEventListener(ev, function () { gsap.to(b, { scale: 1, duration: .25, ease: 'back.out(3)' }); });
        });
    });
})();
