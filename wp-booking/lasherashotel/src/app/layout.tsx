import type { Metadata } from "next";
import { Playfair_Display, Lato } from "next/font/google";
import "./globals.css";
import Navbar from "@/components/ui/Navbar";
import Footer from "@/components/ui/Footer";
import WhatsAppFAB from "@/components/ui/WhatsAppFAB";

const playfair = Playfair_Display({
  variable: "--font-display",
  subsets: ["latin"],
  weight: ["400", "600", "700"],
  display: "swap",
});

const lato = Lato({
  variable: "--font-body",
  subsets: ["latin"],
  weight: ["300", "400", "700"],
  display: "swap",
});

export const metadata: Metadata = {
  title: "Las Heras Hotel — Mar del Plata, Buenos Aires",
  description:
    "Hotel de dos estrellas en el corazón de Mar del Plata. A pasos del mar, con la calidez y hospitalidad argentina. Reservá tu habitación online.",
  keywords: [
    "hotel mar del plata",
    "las heras hotel",
    "hotel centro mar del plata",
    "alojamiento mar del plata",
    "hotel playa buenos aires",
  ],
  openGraph: {
    title: "Las Heras Hotel — Mar del Plata",
    description:
      "A pasos del mar, en el corazón de Mar del Plata. Reservá tu habitación online.",
    type: "website",
    locale: "es_AR",
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="es" className={`${playfair.variable} ${lato.variable}`}>
      <body className="min-h-screen flex flex-col antialiased">
        <Navbar />
        <main className="flex-1">{children}</main>
        <Footer />
        <WhatsAppFAB />
      </body>
    </html>
  );
}
