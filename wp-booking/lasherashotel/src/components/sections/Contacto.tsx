'use client';

import { useState, FormEvent } from 'react';
import { Send, MessageCircle, CheckCircle } from 'lucide-react';

export default function Contacto() {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [submitted, setSubmitted] = useState(false);

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();

    // TODO: Connect to WP endpoint or email service (e.g., SendGrid, Resend)
    console.log('Contact form submitted:', { name, email, subject, message });

    setSubmitted(true);
    setTimeout(() => {
      setSubmitted(false);
      setName('');
      setEmail('');
      setSubject('');
      setMessage('');
    }, 4000);
  };

  return (
    <section id="contacto" className="py-16 sm:py-20 bg-blanco-roto">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 className="section-title">Contactanos</h2>
        <div className="section-separator" />
        <p className="text-center text-foreground/60 mb-10 max-w-2xl mx-auto">
          ¿Tenés alguna consulta? Escribinos y te respondemos a la brevedad.
        </p>

        <div className="grid grid-cols-1 lg:grid-cols-5 gap-8 max-w-5xl mx-auto">
          {/* Form */}
          <div className="lg:col-span-3">
            <form onSubmit={handleSubmit} className="card p-6 sm:p-8">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                  <label className="form-label" htmlFor="contact-name">Nombre</label>
                  <input
                    id="contact-name"
                    type="text"
                    className="form-input"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required
                    placeholder="Tu nombre"
                  />
                </div>
                <div>
                  <label className="form-label" htmlFor="contact-email">Email</label>
                  <input
                    id="contact-email"
                    type="email"
                    className="form-input"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                    placeholder="tu@email.com"
                  />
                </div>
              </div>

              <div className="mb-4">
                <label className="form-label" htmlFor="contact-subject">Asunto</label>
                <input
                  id="contact-subject"
                  type="text"
                  className="form-input"
                  value={subject}
                  onChange={(e) => setSubject(e.target.value)}
                  required
                  placeholder="¿En qué podemos ayudarte?"
                />
              </div>

              <div className="mb-6">
                <label className="form-label" htmlFor="contact-message">Mensaje</label>
                <textarea
                  id="contact-message"
                  className="form-input min-h-[120px] resize-y"
                  value={message}
                  onChange={(e) => setMessage(e.target.value)}
                  required
                  placeholder="Escribí tu mensaje aquí..."
                />
              </div>

              {submitted ? (
                <div className="flex items-center gap-2 text-green-700 bg-green-50 p-4 rounded-lg text-sm font-semibold">
                  <CheckCircle size={18} />
                  ¡Mensaje enviado! Te responderemos pronto.
                </div>
              ) : (
                <button type="submit" className="btn-primary flex items-center gap-2">
                  <Send size={16} />
                  Enviar mensaje
                </button>
              )}
            </form>
          </div>

          {/* WhatsApp & quick info */}
          <div className="lg:col-span-2">
            <div className="card p-6 sm:p-8 text-center">
              <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-green-100 flex items-center justify-center">
                <MessageCircle size={28} className="text-green-600" />
              </div>
              <h3
                className="text-lg font-bold text-azul-marino mb-2"
                style={{ fontFamily: 'var(--font-display)' }}
              >
                Escribinos por WhatsApp
              </h3>
              <p className="text-sm text-foreground/60 mb-5">
                Respondemos rápido. Estamos disponibles de lunes a domingo, de 8 a 22 hs.
              </p>
              <a
                href="https://wa.me/542234567890"
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 bg-green-600 text-white px-6 py-3 rounded-lg font-semibold text-sm hover:bg-green-700 transition-colors"
              >
                <MessageCircle size={18} />
                +54 223 456-7890
              </a>

              <div className="mt-8 pt-6 border-t border-crema">
                <p className="text-xs text-foreground/40 leading-relaxed">
                  También podés llamarnos o enviarnos un email a{' '}
                  <a href="mailto:reservas@lasherashotel.com" className="text-azul-cielo hover:underline">
                    reservas@lasherashotel.com
                  </a>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
