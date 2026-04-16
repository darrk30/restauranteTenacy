<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TuKipu | Soluciones Tecnológicas & Software a Medida</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primario: #FF6B00; 
            --secundario: #00D1FF; 
            --oscuro: #0f172a;
            --texto: #334155;
            --texto-claro: #64748b;
            --fondo: #f8fafc;
            --blanco: #ffffff;
            --gris-disabled: #cbd5e1;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        html { scroll-behavior: smooth; }
        body { background-color: var(--fondo); color: var(--texto); overflow-x: hidden; }

        .container { max-width: 1200px; margin: 0 auto; width: 100%; }

        /* --- NAVEGACIÓN --- */
        nav {
            position: fixed; top: 0; width: 100%; padding: 15px 5%;
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); z-index: 1000;
        }
        .nav-container { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.8rem; font-weight: 800; text-decoration: none; color: var(--oscuro); }
        .logo span { color: var(--primario); }
        .logo b { color: var(--secundario); }

        .nav-links { display: flex; gap: 30px; list-style: none; align-items: center; }
        .nav-links a { color: var(--oscuro); text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { color: var(--primario); }
        .menu-toggle { display: none; font-size: 2rem; cursor: pointer; color: var(--oscuro); }

        /* --- BOTONES --- */
        .btn { padding: 12px 28px; border-radius: 8px; font-weight: 700; text-decoration: none; display: inline-block; transition: 0.3s; cursor: pointer; border: none; text-align: center; }
        .btn-primario { background: var(--primario); color: var(--blanco); box-shadow: 0 4px 15px rgba(255,107,0,0.3); }
        .btn-primario:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(255,107,0,0.4); }
        .btn-oscuro { background: var(--oscuro); color: var(--blanco); }
        .btn-oscuro:hover { background: var(--secundario); color: var(--oscuro); }
        
        .btn-disabled { background: var(--fondo); border: 2px solid var(--gris-disabled); color: var(--texto-claro); cursor: not-allowed; box-shadow: none; pointer-events: none; }

        /* --- SECCIONES COMUNES --- */
        section { padding: 100px 5%; }
        .titulo-seccion { text-align: center; margin-bottom: 50px; }
        .titulo-seccion h2 { font-size: 2.5rem; color: var(--oscuro); margin-bottom: 15px; }
        .titulo-seccion p { color: var(--texto-claro); max-width: 600px; margin: 0 auto; font-size: 1.1rem; }

        /* --- 1. HERO --- */
        #inicio { 
            min-height: 100vh; display: flex; align-items: center;
            background: radial-gradient(circle at top right, #e0f2fe 0%, #ffffff 100%);
            padding-top: 120px;
        }
        .hero-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: center; }
        .badge { background: rgba(0,209,255,0.1); color: #0284c7; padding: 8px 16px; border-radius: 50px; font-weight: 700; font-size: 0.85rem; margin-bottom: 20px; display: inline-block; letter-spacing: 1px; }
        .hero-content h1 { font-size: clamp(2.5rem, 5vw, 4rem); line-height: 1.1; color: var(--oscuro); margin-bottom: 25px; letter-spacing: -1px; }
        .hero-content h1 span { color: var(--primario); }
        .hero-content p { font-size: 1.15rem; color: var(--texto-claro); margin-bottom: 40px; }
        .hero-img { text-align: center; }
        .hero-img img { width: 100%; max-width: 500px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); animation: float 6s ease-in-out infinite; }

        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-20px); } }

        /* --- 2. ECOSISTEMA SAAS (SLIDER SUAVE) --- */
        #saas { background: var(--blanco); overflow: hidden; padding-left: 0; padding-right: 0; }
        
        .slider-wrapper {
            width: 100%;
            position: relative;
            overflow: hidden;
            padding: 20px 0 50px;
            -webkit-mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
            mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
        }

        .saas-slider-track {
            display: flex;
            gap: 30px;
            width: max-content;
            /* El JavaScript se encargará de activar esta clase */
        }

        /* Clase para la animación */
        .scrolling-active {
             animation: scroll-suave 40s linear infinite;
        }

        .saas-slider-track:hover {
            animation-play-state: paused;
        }

        .saas-card { 
            background: var(--fondo); border-radius: 20px; 
            border: 1px solid #e2e8f0; transition: 0.3s; overflow: hidden;
            display: flex; flex-direction: column;
            width: 350px; 
            flex-shrink: 0;
        }
        .saas-card:hover { border-color: var(--primario); box-shadow: 0 20px 40px rgba(0,0,0,0.08); transform: translateY(-5px); }
        .saas-card-img { width: 100%; height: 200px; object-fit: cover; }
        .saas-content { padding: 30px; display: flex; flex-direction: column; flex-grow: 1; }
        .saas-icon { font-size: 2rem; display: inline-block; background: var(--blanco); padding: 10px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transform: translateY(-50px); margin-bottom: -35px; width: fit-content; }
        .saas-content h3 { font-size: 1.3rem; color: var(--oscuro); margin-bottom: 10px; }
        .saas-content p { color: var(--texto-claro); font-size: 0.95rem; margin-bottom: 20px; flex-grow: 1; }
        
        .card-actions { margin-top: 20px; display: flex; flex-direction: column; }

        @keyframes scroll-suave {
            0% { transform: translateX(0); }
            /* Desplazamiento ajustado a la mitad del contenedor (para compensar el clonado en JS) */
            100% { transform: translateX(calc(-50% - 15px)); }
        }

        /* --- 3. SOFTWARE A MEDIDA --- */
        #a-medida { background: var(--oscuro); color: var(--blanco); }
        #a-medida .titulo-seccion h2 { color: var(--blanco); }
        #a-medida .titulo-seccion p { color: #94a3b8; }
        .medida-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: center; }
        .medida-text p { font-size: 1.1rem; margin-bottom: 25px; line-height: 1.8; color: #cbd5e1; }
        .medida-img img { width: 100%; border-radius: 20px; opacity: 0.9; }

        /* --- 4. TECNOLOGÍAS --- */
        #tecnologias { background: var(--fondo); }
        .tech-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 20px; }
        .tech-item { 
            background: var(--blanco); padding: 15px 30px; border-radius: 12px; font-weight: 700; 
            color: var(--oscuro); border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            transition: 0.3s; display: flex; align-items: center; gap: 10px; font-size: 1.1rem;
        }
        .tech-item:hover { transform: scale(1.05); border-color: var(--secundario); color: var(--secundario); }

        /* --- FOOTER COMPLETO --- */
        footer { background: #020617; padding: 60px 5% 30px; color: var(--texto-claro); border-top: 1px solid rgba(255,255,255,0.05); }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 50px; margin-bottom: 40px; }
        .footer-brand p { margin-top: 15px; font-size: 0.9rem; line-height: 1.6; max-width: 300px; }
        .footer-title { color: var(--blanco); font-size: 1.1rem; margin-bottom: 20px; font-weight: 600; }
        .footer-links { list-style: none; }
        .footer-links li { margin-bottom: 12px; }
        .footer-links a { color: var(--texto-claro); text-decoration: none; font-size: 0.9rem; transition: 0.3s; }
        .footer-links a:hover { color: var(--secundario); }
        .footer-bottom { text-align: center; padding-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.85rem; }

        /* --- RESPONSIVE --- */
        @media (max-width: 900px) {
            .hero-grid, .medida-grid, .footer-grid { grid-template-columns: 1fr; text-align: center; }
            .medida-img { grid-row: 1; } 
            .nav-links { display: none; flex-direction: column; position: absolute; top: 100%; left: 0; width: 100%; background: var(--blanco); padding: 20px; box-shadow: 0 10px 10px rgba(0,0,0,0.1); }
            .nav-links.active { display: flex; }
            .menu-toggle { display: block; }
        }

        .reveal { opacity: 0; transform: translateY(40px); transition: all 0.8s ease-out; }
        .reveal.active { opacity: 1; transform: translateY(0); }
    </style>
</head>
<body>

    <nav>
        <div class="container nav-container">
            <a href="#inicio" class="logo"><span>Tu</span><b>Kipu</b></a>
            <div class="menu-toggle" id="mobile-menu">☰</div>
            <ul class="nav-links" id="nav-links">
                <li><a href="#inicio">Inicio</a></li>
                <li><a href="#saas">Productos</a></li>
                <li><a href="#a-medida">Software a Medida</a></li>
                <li><a href="#tecnologias">Tecnologías</a></li>
                <li><a href="#contacto" class="btn btn-primario" style="padding: 8px 20px; color: white;">Contactar</a></li>
            </ul>
        </div>
    </nav>

    <section id="inicio">
        <div class="container hero-grid reveal">
            <div class="hero-content">
                <div class="badge">ECOSISTEMA SaaS MULTITENANCY</div>
                <h1>Potencia tu negocio con software <span>diseñado para crecer.</span></h1>
                <p>Soluciones tecnológicas escalables: desde sistemas de punto de venta (POS) y cartas virtuales, hasta complejas plataformas de gestión para hoteles y gimnasios.</p>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <a href="#saas" class="btn btn-primario">Ver Sistemas</a>
                    <a href="#a-medida" class="btn btn-oscuro">Cotizar a Medida</a>
                </div>
            </div>
            <div class="hero-img">
                <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=800&q=80" alt="Dashboard TuKipu">
            </div>
        </div>
    </section>

    <section id="saas">
        <div class="container">
            <div class="titulo-seccion reveal">
                <h2>Nuestras Soluciones SaaS</h2>
                <p>Sistemas listos para usar que digitalizan y automatizan las operaciones diarias de tu empresa.</p>
            </div>
        </div>

        <div class="slider-wrapper reveal">
            <div class="saas-slider-track" id="slider-track">
                
                <div class="saas-card">
                    <img src="https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&w=600&q=80" class="saas-card-img" alt="Restaurantes">
                    <div class="saas-content">
                        <div class="saas-icon">🍔</div>
                        <h3>Restaurantes & POS</h3>
                        <p>Gestión gastronómica total con nuestro sistema Kipu y menús digitales interactivos.</p>
                        <div class="card-actions">
                            <a href="https://restaurant-central.tukipu.cloud/pdv/login" class="btn btn-primario">Ver Demo</a>
                        </div>
                    </div>
                </div>

                <div class="saas-card">
                    <img src="https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=600&q=80" class="saas-card-img" alt="Minimarkets">
                    <div class="saas-content">
                        <div class="saas-icon">🛒</div>
                        <h3>Minimarkets & Retail</h3>
                        <p>Control de inventario en tiempo real, lector de códigos y arqueos de caja precisos.</p>
                        <div class="card-actions">
                            <a href="#" class="btn btn-disabled">Próximamente</a>
                        </div>
                    </div>
                </div>

                <div class="saas-card">
                    <img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=600&q=80" class="saas-card-img" alt="Hoteles">
                    <div class="saas-content">
                        <div class="saas-icon">🏨</div>
                        <h3>Hoteles & Hospedajes</h3>
                        <p>Calendario visual de reservas, control de habitaciones y housekeeping integrado.</p>
                        <div class="card-actions">
                            <a href="#" class="btn btn-disabled">Próximamente</a>
                        </div>
                    </div>
                </div>

                <div class="saas-card">
                    <img src="https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=600&q=80" class="saas-card-img" alt="Gimnasios">
                    <div class="saas-content">
                        <div class="saas-icon">🏋️‍♂️</div>
                        <h3>Gimnasios & Clubs</h3>
                        <p>Automatiza el cobro de membresías y controla los accesos de tus socios de forma segura.</p>
                        <div class="card-actions">
                            <a href="#" class="btn btn-disabled">Próximamente</a>
                        </div>
                    </div>
                </div>

                <div class="saas-card">
                    <img src="https://images.unsplash.com/photo-1577896851231-70ef18881754?auto=format&fit=crop&w=600&q=80" class="saas-card-img" alt="Academias">
                    <div class="saas-content">
                        <div class="saas-icon">🎓</div>
                        <h3>Academias & Cursos</h3>
                        <p>Administra registros de alumnos, control de asistencias y mensualidades fácilmente.</p>
                        <div class="card-actions">
                            <a href="#" class="btn btn-disabled">Próximamente</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <section id="a-medida">
        <div class="container">
            <div class="titulo-seccion reveal">
                <h2>¿Tienes una idea única? <br><span style="color: var(--primario);">Hago Software a Medida</span></h2>
                <p>Si las soluciones estándar no encajan con tu modelo de negocio, construimos una arquitectura desde cero.</p>
            </div>
            <div class="medida-grid reveal">
                <div class="medida-text">
                    <p>Como desarrollador Full-Stack, me especializo en entender la lógica de tu negocio y traducirla en código eficiente. Desde plataformas de rastreo, sistemas e-commerce B2B, hasta intranets corporativas altamente personalizadas.</p>
                    <p><strong>Mi enfoque:</strong> Bases de datos seguras, código limpio y una interfaz de usuario súper intuitiva para que tu equipo no pierda tiempo aprendiendo a usar un sistema complejo.</p>
                    <a href="#contacto" class="btn btn-primario" style="margin-top: 20px;">Hablemos de tu proyecto</a>
                </div>
                <div class="medida-img">
                    <img src="https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&w=800&q=80" alt="Código y Programación">
                </div>
            </div>
        </div>
    </section>

    <section id="tecnologias">
        <div class="container">
            <div class="titulo-seccion reveal">
                <h2>Stack Tecnológico</h2>
                <p>Utilizo las herramientas más modernas y robustas del mercado para garantizar que tu software sea rápido, seguro y escalable.</p>
            </div>
            <div class="tech-grid reveal">
                <div class="tech-item"><span style="color: #FF2D20;">●</span> Laravel</div>
                <div class="tech-item"><span style="color: #fbbf24;">●</span> Livewire</div>
                <div class="tech-item"><span style="color: #0ea5e9;">●</span> FilamentPHP</div>
                <div class="tech-item"><span style="color: #6db33f;">●</span> Spring Boot</div>
                <div class="tech-item"><span style="color: #61DAFB;">●</span> React</div>
                <div class="tech-item"><span style="color: #3178C6;">●</span> TypeScript</div>
                <div class="tech-item"><span style="color: #F7DF1E;">●</span> JavaScript</div>
                <div class="tech-item"><span style="color: #4479A1;">●</span> MySQL</div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container footer-grid">
            <div class="footer-brand">
                <div class="logo" style="font-size: 1.8rem; color: var(--blanco);"><span>Tu</span><b style="color: var(--secundario);">Kipu</b></div>
                <p>Desarrollo de software y soluciones tecnológicas para empresas que buscan escalar y modernizar sus operaciones diarias.</p>
            </div>
            <div>
                <h4 class="footer-title">Productos</h4>
                <ul class="footer-links">
                    <li><a href="#">POS Restaurantes</a></li>
                    <li><a href="#">Cartas Virtuales</a></li>
                    <li><a href="#">Gestión Hotelera</a></li>
                    <li><a href="#">Sistema de Minimarkets</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-title">Contacto</h4>
                <ul class="footer-links">
                    <li>📍 Chiclayo, Perú</li>
                    <li><a href="#">📞 WhatsApp: Escríbenos</a></li>
                    <li><a href="#">✉️ soporte@tukipu.com</a></li>
                </ul>
            </div>
        </div>
        <div class="container footer-bottom">
            <p>&copy; 2026 TuKipu. Desarrollado por Kevin Rivera. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
        // 1. Menú Móvil
        const menuBtn = document.getElementById('mobile-menu');
        const navLinks = document.getElementById('nav-links');
        menuBtn.addEventListener('click', () => navLinks.classList.toggle('active'));
        document.querySelectorAll('.nav-links a').forEach(a => {
            a.addEventListener('click', () => navLinks.classList.remove('active'));
        });

        // 2. Animación Reveal
        function reveal() {
            var reveals = document.querySelectorAll(".reveal");
            for (var i = 0; i < reveals.length; i++) {
                var windowHeight = window.innerHeight;
                var elementTop = reveals[i].getBoundingClientRect().top;
                var elementVisible = 10;
                if (elementTop < windowHeight - elementVisible) {
                    reveals[i].classList.add("active");
                }
            }
        }
        window.addEventListener("scroll", reveal);
        reveal();

        // 3. MAGIA DEL SLIDER INFINITO (Clonación Automática)
        document.addEventListener('DOMContentLoaded', () => {
            const track = document.getElementById('slider-track');
            
            // Clonamos todas las tarjetas originales
            const cards = track.innerHTML;
            
            // Las pegamos justo a continuación (Esto permite el ciclo sin fin)
            track.innerHTML += cards;
            
            // Activamos la clase de animación una vez clonadas
            track.classList.add('scrolling-active');
        });
    </script>
</body>
</html>