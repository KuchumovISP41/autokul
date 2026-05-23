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
    console.log('✅ Автокул СТО — главная страница загружена');
});

// Интерактив: тема, карусель, переключение вида, анимация при скролле
const body = document.body;
const toggle = document.getElementById('themeToggle');
if (toggle) toggle.addEventListener('click', ()=> body.classList.toggle('theme-dark'));

const slides = document.querySelectorAll('.carousel-slide');
if (slides.length) {
  let i=0; setInterval(()=>{slides[i].classList.remove('active'); i=(i+1)%slides.length; slides[i].classList.add('active');},3000);
}
const gridBtn=document.getElementById('viewGrid');
const listBtn=document.getElementById('viewList');
const servicesGrid=document.querySelector('.services-grid');
if(gridBtn&&listBtn&&servicesGrid){
 gridBtn.onclick=()=>{servicesGrid.classList.remove('list-view')};
 listBtn.onclick=()=>{servicesGrid.classList.add('list-view')};
}
const io = new IntersectionObserver((entries)=>entries.forEach(e=>{if(e.isIntersecting)e.target.classList.add('in-view')}),{threshold:0.1});
document.querySelectorAll('.service-card,.advantage-card,.step-card').forEach(el=>io.observe(el));
