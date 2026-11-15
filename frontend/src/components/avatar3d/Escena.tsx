import { ContactShadows, Html } from '@react-three/drei';
import { invalidate, useFrame, useThree } from '@react-three/fiber';
import { useEffect, useMemo, useRef, type RefObject } from 'react';
import * as THREE from 'three';
import {
  COLOR_ESTADO,
  etiquetaZona,
  formatoHolgura,
  type AnalisisAjuste,
  type Corte,
  type Medidas,
} from '../../lib/fit';
import type { CategoriaPrenda3D } from './categoria';
import { construirCuerpo, liberarCuerpo, type GeneroAvatar, type ModeloCuerpo } from './cuerpo';
import { smoothstep } from './geometria';
import { construirPrenda, liberarPrenda, type EtiquetaZona3D } from './prenda';

/** Objetivo de cámara. Vive en un ref para que arrastrar no vuelva a renderizar React. */
export type Vista = { azimut: number; zoom: number };

export type EscenaProps = {
  genero: GeneroAvatar;
  medidas: Medidas;
  corte: Corte;
  analisis: AnalisisAjuste;
  deltaTalla: number;
  categoria: CategoriaPrenda3D;
  colorHex: string;
  mostrarMapa: boolean;
  tema: 'light' | 'dark';
  vista: RefObject<Vista>;
};

const FOV = 30;

function Camara({ vista, H, focoY, lateral }: { vista: RefObject<Vista>; H: number; focoY: number; lateral: number }) {
  const camera = useThree((s) => s.camera);
  const size = useThree((s) => s.size);
  const actual = useRef({ az: vista.current.azimut, zoom: vista.current.zoom, lateral });

  useFrame((_, dt) => {
    const objetivo = vista.current;
    const a = actual.current;
    const k = 1 - Math.exp(-Math.min(dt, 0.05) * 9);
    a.az += (objetivo.azimut - a.az) * k;
    a.zoom += (objetivo.zoom - a.zoom) * k;
    a.lateral += (lateral - a.lateral) * k;

    const mitadFov = THREE.MathUtils.degToRad(FOV / 2);
    const porAlto = (H * 1.13) / 2 / Math.tan(mitadFov);
    const porAncho = 1.05 / 2 / Math.tan(mitadFov) / (size.width / size.height);
    const d = Math.max(porAlto, porAncho) / a.zoom;
    const y = THREE.MathUtils.lerp(H * 0.5, focoY, smoothstep(1, 1.7, a.zoom));
    const rad = THREE.MathUtils.degToRad(a.az);

    // Desplazamiento lateral en el plano de cámara: deja sitio a las etiquetas sin girar la vista.
    const ox = Math.cos(rad) * a.lateral;
    const oz = -Math.sin(rad) * a.lateral;
    camera.position.set(Math.sin(rad) * d + ox, y + d * 0.045, Math.cos(rad) * d + oz);
    camera.lookAt(ox, y, oz);

    if (
      Math.abs(objetivo.azimut - a.az) > 0.05 ||
      Math.abs(objetivo.zoom - a.zoom) > 0.002 ||
      Math.abs(lateral - a.lateral) > 0.001
    ) {
      invalidate();
    }
  });

  useEffect(() => {
    invalidate();
  }, [H, focoY, size, lateral]);

  return null;
}

/** Textura de punto fino para dar relieve a la tela. */
function crearTexturaTela(): THREE.CanvasTexture {
  const lado = 64;
  const lienzo = document.createElement('canvas');
  lienzo.width = lado;
  lienzo.height = lado;
  const ctx = lienzo.getContext('2d')!;
  ctx.fillStyle = '#808080';
  ctx.fillRect(0, 0, lado, lado);
  for (let y = 0; y < lado; y += 4) {
    for (let x = 0; x < lado; x += 4) {
      const claro = (x / 4 + y / 4) % 2 === 0;
      ctx.fillStyle = claro ? '#9a9a9a' : '#6c6c6c';
      ctx.fillRect(x, y, 2, 3);
    }
  }
  const tex = new THREE.CanvasTexture(lienzo);
  tex.wrapS = THREE.RepeatWrapping;
  tex.wrapT = THREE.RepeatWrapping;
  tex.repeat.set(26, 26);
  tex.anisotropy = 4;
  return tex;
}

function Maniqui({ modelo, tema, prendaClara }: { modelo: ModeloCuerpo; tema: 'light' | 'dark'; prendaClara: boolean }) {
  // Con prendas claras el maniquí se oscurece para que la silueta de la prenda se distinga.
  const color = prendaClara ? (tema === 'dark' ? '#7b7872' : '#9e9990') : tema === 'dark' ? '#b9b5ad' : '#d8d3ca';
  const material = useMemo(
    () => new THREE.MeshStandardMaterial({ color, roughness: 0.58, metalness: 0.02 }),
    [color],
  );
  useEffect(() => () => material.dispose(), [material]);

  const g = modelo.geometrias;
  const h = modelo.hombro;
  return (
    <group>
      <mesh geometry={g.torso} material={material} castShadow receiveShadow />
      <mesh geometry={g.cabeza} material={material} castShadow receiveShadow />
      {([1, -1] as const).map((lado, i) => (
        <group key={lado}>
          <mesh geometry={g.piernas[i]} material={material} castShadow receiveShadow />
          <mesh
            geometry={g.pie}
            material={material}
            position={[lado * g.posicionPie[0], g.posicionPie[1], g.posicionPie[2]]}
            castShadow
          />
          <group position={[lado * h.x, h.y, h.z]} rotation={[0, 0, lado * h.angulo]}>
            <mesh geometry={g.brazo} material={material} castShadow receiveShadow />
          </group>
        </group>
      ))}
    </group>
  );
}

/** Borde exterior del brazo a una altura, para que las etiquetas no queden detrás. */
function bordeBrazo(m: ModeloCuerpo, y: number): number {
  const caida = m.hombro.y - y;
  if (caida < 0 || caida > m.largoBrazo * 1.32) return 0;
  return m.hombro.x + Math.sin(m.hombro.angulo) * caida + m.radioBrazo * 1.3;
}

function Etiquetas({
  etiquetas,
  analisis,
  modelo,
}: {
  etiquetas: EtiquetaZona3D[];
  analisis: AnalisisAjuste;
  modelo: ModeloCuerpo;
}) {
  const grupo = useRef<THREE.Group>(null);
  // El grupo mira siempre a cámara: las etiquetas quedan a la derecha de la silueta al girar.
  useFrame(({ camera }) => {
    if (grupo.current) grupo.current.rotation.y = Math.atan2(camera.position.x, camera.position.z);
  });

  // Separa las etiquetas que caerían una encima de otra (cadera y largo suelen coincidir).
  const separacion = 0.062 * modelo.H;
  const colocadas = [...etiquetas].sort((a, b) => b.y - a.y);
  const alturas = new Map<string, number>();
  colocadas.forEach((e, i) => {
    const previa = i > 0 ? alturas.get(colocadas[i - 1].clave)! : Infinity;
    alturas.set(e.clave, Math.min(e.y, previa - separacion));
  });

  return (
    <group ref={grupo}>
      {etiquetas.map((e) => {
        const zona = analisis.zonas.find((z) => z.clave === e.clave);
        if (!zona) return null;
        const x = Math.max(e.radio, bordeBrazo(modelo, e.y)) + 0.02;
        return (
          <Html key={e.clave} position={[x, alturas.get(e.clave) ?? e.y, 0]} zIndexRange={[5, 0]} style={{ pointerEvents: 'none' }}>
            <div className="flex -translate-y-1/2 items-center">
              <span className="block h-px w-4 sm:w-7" style={{ background: COLOR_ESTADO[zona.estado] }} />
              <span
                className="whitespace-nowrap border-l-[3px] bg-paper/95 px-2 py-1 shadow-sm"
                style={{ borderColor: COLOR_ESTADO[zona.estado] }}
              >
                <span className="block font-narrow text-[10px] font-semibold uppercase leading-none tracking-[0.1em] text-soft">
                  {zona.zona} <span className="text-ink">{formatoHolgura(zona.holguraCm)}</span>
                </span>
                <span className="mt-1 block text-[12px] font-semibold leading-none text-ink">{etiquetaZona(zona)}</span>
              </span>
            </div>
          </Html>
        );
      })}
    </group>
  );
}

export function Escena({
  genero,
  medidas,
  corte,
  analisis,
  deltaTalla,
  categoria,
  colorHex,
  mostrarMapa,
  tema,
  vista,
}: EscenaProps) {
  const modelo = useMemo(
    () => construirCuerpo(medidas, genero),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [medidas.h, medidas.chest, medidas.waist, medidas.hip, genero],
  );
  useEffect(() => () => liberarCuerpo(modelo), [modelo]);

  const prenda = useMemo(
    () => construirPrenda(modelo, analisis, categoria, corte, deltaTalla),
    [modelo, analisis, categoria, corte, deltaTalla],
  );
  useEffect(() => () => liberarPrenda(prenda), [prenda]);

  const texturaTela = useMemo(() => crearTexturaTela(), []);
  useEffect(() => () => texturaTela.dispose(), [texturaTela]);

  const tela = useMemo(() => {
    const base = new THREE.Color(colorHex);
    const esMalla = categoria === 'mallas';
    return new THREE.MeshPhysicalMaterial({
      color: mostrarMapa ? '#ffffff' : base,
      vertexColors: mostrarMapa,
      roughness: mostrarMapa ? 0.9 : esMalla ? 0.62 : 0.86,
      metalness: 0,
      sheen: mostrarMapa ? 0 : 0.7,
      sheenRoughness: 0.55,
      sheenColor: base.clone().lerp(new THREE.Color('#ffffff'), 0.55),
      bumpMap: mostrarMapa ? null : texturaTela,
      bumpScale: 0.35,
      side: THREE.DoubleSide,
    });
  }, [colorHex, mostrarMapa, categoria, texturaTela]);
  useEffect(() => () => tela.dispose(), [tela]);

  const h = modelo.hombro;
  const oscuro = tema === 'dark';
  const hsl = { h: 0, s: 0, l: 0 };
  new THREE.Color(colorHex).getHSL(hsl);
  const prendaClara = !mostrarMapa && hsl.l > 0.6;

  return (
    <>
      <Camara vista={vista} H={modelo.H} focoY={prenda.centroY} lateral={mostrarMapa ? 0.14 : 0} />

      <hemisphereLight args={[oscuro ? '#f3efe8' : '#ffffff', oscuro ? '#3a3935' : '#b9b4aa', oscuro ? 0.95 : 1.15]} />
      <directionalLight
        castShadow
        position={[1.9, 3.6, 3.1]}
        intensity={2.1}
        shadow-mapSize-width={1024}
        shadow-mapSize-height={1024}
        shadow-camera-left={-1.3}
        shadow-camera-right={1.3}
        shadow-camera-top={2.4}
        shadow-camera-bottom={-0.3}
        shadow-camera-near={0.5}
        shadow-camera-far={9}
        shadow-bias={-0.0004}
        shadow-normalBias={0.03}
      />
      <directionalLight position={[-3, 1.8, 1.6]} intensity={0.75} color="#e9eef6" />
      <directionalLight position={[0.6, 2.4, -3.4]} intensity={1.1} color="#fff6ea" />

      <Maniqui modelo={modelo} tema={tema} prendaClara={prendaClara} />

      <mesh geometry={prenda.cuerpo} material={tela} castShadow receiveShadow />
      {prenda.manga &&
        ([1, -1] as const).map((lado) => (
          <group key={lado} position={[lado * h.x, h.y, h.z]} rotation={[0, 0, lado * h.angulo]}>
            <mesh geometry={prenda.manga!} material={tela} castShadow receiveShadow />
          </group>
        ))}
      {prenda.perneras?.map((geo, i) => (
        <mesh key={i} geometry={geo} material={tela} castShadow receiveShadow />
      ))}

      {mostrarMapa && <Etiquetas etiquetas={prenda.etiquetas} analisis={analisis} modelo={modelo} />}

      {/* Peana y sombra de contacto */}
      <mesh rotation={[-Math.PI / 2, 0, 0]} position={[0, -0.002, 0]}>
        <circleGeometry args={[0.62, 72]} />
        <meshStandardMaterial color={oscuro ? '#3b3b37' : '#e6e3dc'} roughness={0.95} />
      </mesh>
      <ContactShadows position={[0, 0.001, 0]} opacity={oscuro ? 0.55 : 0.4} scale={2.2} blur={2.6} far={1.1} resolution={512} />
    </>
  );
}
