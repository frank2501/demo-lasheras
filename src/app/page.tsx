import Hero from '@/components/sections/Hero';
import Habitaciones from '@/components/sections/Habitaciones';
import Servicios from '@/components/sections/Servicios';
import Galeria from '@/components/sections/Galeria';
import Resenias from '@/components/sections/Resenias';
import Ubicacion from '@/components/sections/Ubicacion';

export default function Home() {
  return (
    <>
      <Hero />
      <Habitaciones />
      <Servicios />
      <Galeria />
      <Resenias />
      <Ubicacion />
    </>
  );
}
