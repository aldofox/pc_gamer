document.addEventListener('DOMContentLoaded', function() {

    /* HERO BANNER */
    // O seletor deve ser o container principal do Swiper
    const heroBannerContainer = document.querySelector('.hero-banner.swiper-container');
    if (heroBannerContainer) { // Verifica se o container existe
        new Swiper(heroBannerContainer, {
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                // Seleciona a paginação DENTRO do container do hero banner
                el: heroBannerContainer.querySelector('.swiper-pagination'),
                clickable: true,
            },
            navigation: {
                // Seleciona os botões de navegação DENTRO do container do hero banner
                nextEl: heroBannerContainer.querySelector('.swiper-button-next'),
                prevEl: heroBannerContainer.querySelector('.swiper-button-prev'),
            },
            effect: 'fade',
            fadeEffect: { crossFade: true },
            speed: 800, // Adicionei a velocidade para o efeito fade
        });
    }

    /* PRODUCT CAROUSEL */
    // Itera sobre CADA carrossel de produto
    document.querySelectorAll('.swiper.product-carousel').forEach(carouselElement => {
        // Não precisamos do 'section' aqui, pois os botões estão dentro do 'carouselElement'
        // ou podem ser selecionados diretamente a partir dele.

        new Swiper(carouselElement, {
            slidesPerView: 1, // Começa com 1 para mobile
            spaceBetween: 20,

            loop: true,
            loopFillGroupWithBlank: true,

            autoplay: {
                delay: 3500,
                disableOnInteraction: false,
                pauseOnMouseEnter: true
            },

            navigation: {
                // Seleciona os botões de navegação DENTRO do container do carrossel atual
                nextEl: carouselElement.querySelector('.swiper-button-next'),
                prevEl: carouselElement.querySelector('.swiper-button-prev'),
            },

            breakpoints: {
                0:    { slidesPerView: 1 },
                640:  { slidesPerView: 2 },
                768:  { slidesPerView: 3 },
                1024: { slidesPerView: 4 },
                1200: { slidesPerView: 5 } // Ajustei para 5, pois seu HTML mostra 5 cards no grid
            },

            observer: true,
            observeParents: true,
            watchOverflow: true,
        });

    });

    /* MENU MOBILE */
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mainNav = document.querySelector('.main-nav');

    if (mobileMenuToggle && mainNav) {
        mobileMenuToggle.addEventListener('click', function() {
            mainNav.classList.toggle('active');
        });
    }
});