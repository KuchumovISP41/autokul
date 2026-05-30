/**
 * main.js — Главный скрипт сайта
 * Сайт автосервиса «Автокул СТО»
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // --- Бургер-меню для мобильных ---
    const burgerBtn = document.querySelector('.burger');
    const navMenu = document.querySelector('.nav');
    
    if (burgerBtn && navMenu) {
        burgerBtn.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            
            // Анимация бургера в крестик (опционально)
            const lines = burgerBtn.querySelectorAll('.burger__line');
            if (navMenu.classList.contains('active')) {
                lines[0].style.transform = 'rotate(45deg) translate(5px, 6px)';
                lines[1].style.opacity = '0';
                lines[2].style.transform = 'rotate(-45deg) translate(5px, -6px)';
            } else {
                lines[0].style.transform = 'none';
                lines[1].style.opacity = '1';
                lines[2].style.transform = 'none';
            }
        });
        
        // Закрытие меню при клике на ссылку
        navMenu.querySelectorAll('.nav__link').forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('active');
                const lines = burgerBtn.querySelectorAll('.burger__line');
                lines[0].style.transform = 'none';
                lines[1].style.opacity = '1';
                lines[2].style.transform = 'none';
            });
        });
    }
    
    // --- Плавная прокрутка для якорных ссылок (для старых браузеров) ---
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
    
    // --- Активный пункт меню (подсветка текущего раздела при скролле) ---
    // Пока оставим заглушку, пригодится позже

    // --- Смена темы без перезагрузки ---
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('theme-dark');
        }
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('theme-dark');
            localStorage.setItem('theme', document.body.classList.contains('theme-dark') ? 'dark' : 'light');
        });
    }

    console.log('✅ Автокул СТО — главная страница загружена');
});

// --- Единая маска телефона и мягкая клиентская валидация полей ---
document.addEventListener('DOMContentLoaded', function() {
    const formatPhone = (value) => {
        let digits = value.replace(/\D/g, '');
        if (digits.startsWith('8')) digits = '7' + digits.slice(1);
        if (digits.startsWith('7')) digits = digits.slice(1);
        digits = digits.slice(0, 10);

        let result = '+7';
        if (digits.length > 0) result += ' (' + digits.slice(0, 3);
        if (digits.length >= 3) result += ')';
        if (digits.length > 3) result += ' ' + digits.slice(3, 6);
        if (digits.length > 6) result += '-' + digits.slice(6, 8);
        if (digits.length > 8) result += '-' + digits.slice(8, 10);
        return result.slice(0, 18);
    };

    document.querySelectorAll('input[type="tel"], input[data-phone-mask]').forEach((input) => {
        input.setAttribute('maxlength', '18');
        input.setAttribute('placeholder', input.getAttribute('placeholder') || '+7 (900) 123-45-67');
        input.addEventListener('input', () => {
            input.value = formatPhone(input.value);
        });
        input.addEventListener('blur', () => {
            if (input.value.trim() && input.value.length < 18) {
                input.setCustomValidity('Введите номер телефона полностью: +7 (XXX) XXX-XX-XX');
            } else {
                input.setCustomValidity('');
            }
        });
    });

    document.querySelectorAll('input[type="date"]').forEach((input) => {
        const today = new Date();
        const max = new Date();
        max.setFullYear(today.getFullYear() + 2);
        const toISO = (date) => date.toISOString().slice(0, 10);
        if (!input.min) input.min = toISO(today);
        if (!input.max) input.max = toISO(max);
    });

    document.querySelectorAll('input[type="search"], input[id*="Search"], input[name="search"]').forEach((input) => {
        input.setAttribute('maxlength', '100');
        input.addEventListener('input', () => {
            input.value = input.value.replace(/[^\p{L}\p{N}\- ]/gu, '').slice(0, 100);
        });
    });
});
