import { StrictMode, Suspense, lazy } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { AppLayout } from './components/AppLayout';
import { KlosetProvider } from './store/KlosetContext';
import { CatalogPage } from './pages/CatalogPage';
import './index.css';

// La portada va en el bundle inicial (es el LCP); el resto se carga bajo demanda.
const ProductPage = lazy(() => import('./pages/ProductPage').then((m) => ({ default: m.ProductPage })));
const MeasurePage = lazy(() => import('./pages/MeasurePage').then((m) => ({ default: m.MeasurePage })));
const ResultPage = lazy(() => import('./pages/ResultPage').then((m) => ({ default: m.ResultPage })));
const AvatarPage = lazy(() => import('./pages/AvatarPage').then((m) => ({ default: m.AvatarPage })));
const CartPage = lazy(() => import('./pages/CartPage').then((m) => ({ default: m.CartPage })));
const CheckoutPage = lazy(() => import('./pages/CheckoutPage').then((m) => ({ default: m.CheckoutPage })));
const OrdersPage = lazy(() => import('./pages/OrdersPage').then((m) => ({ default: m.OrdersPage })));
const AuthPage = lazy(() => import('./pages/AuthPage').then((m) => ({ default: m.AuthPage })));
const AccountPage = lazy(() => import('./pages/AccountPage').then((m) => ({ default: m.AccountPage })));
const CoveragePage = lazy(() => import('./pages/CoveragePage').then((m) => ({ default: m.CoveragePage })));
const AboutPage = lazy(() => import('./pages/AboutPage').then((m) => ({ default: m.AboutPage })));
const ContactPage = lazy(() => import('./pages/ContactPage').then((m) => ({ default: m.ContactPage })));
const LegalPage = lazy(() => import('./pages/LegalPage').then((m) => ({ default: m.LegalPage })));

function Cargando() {
  return <p className="py-16 text-center text-soft">Cargando…</p>;
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <KlosetProvider>
      <BrowserRouter>
        <Routes>
          <Route element={<AppLayout />}>
            <Route index element={<CatalogPage />} />
            <Route
              path="producto/:url"
              element={
                <Suspense fallback={<Cargando />}>
                  <ProductPage />
                </Suspense>
              }
            />
            <Route
              path="medidas"
              element={
                <Suspense fallback={<Cargando />}>
                  <MeasurePage />
                </Suspense>
              }
            />
            <Route
              path="resultado"
              element={
                <Suspense fallback={<Cargando />}>
                  <ResultPage />
                </Suspense>
              }
            />
            <Route
              path="avatar"
              element={
                <Suspense fallback={<Cargando />}>
                  <AvatarPage />
                </Suspense>
              }
            />
            <Route
              path="bolsa"
              element={
                <Suspense fallback={<Cargando />}>
                  <CartPage />
                </Suspense>
              }
            />
            <Route
              path="pago"
              element={
                <Suspense fallback={<Cargando />}>
                  <CheckoutPage />
                </Suspense>
              }
            />
            <Route
              path="pedidos"
              element={
                <Suspense fallback={<Cargando />}>
                  <OrdersPage />
                </Suspense>
              }
            />
            <Route
              path="entrar"
              element={
                <Suspense fallback={<Cargando />}>
                  <AuthPage />
                </Suspense>
              }
            />
            <Route
              path="cuenta"
              element={
                <Suspense fallback={<Cargando />}>
                  <AccountPage />
                </Suspense>
              }
            />
            <Route
              path="cobertura"
              element={
                <Suspense fallback={<Cargando />}>
                  <CoveragePage />
                </Suspense>
              }
            />
            <Route
              path="nosotros"
              element={
                <Suspense fallback={<Cargando />}>
                  <AboutPage />
                </Suspense>
              }
            />
            <Route
              path="contacto"
              element={
                <Suspense fallback={<Cargando />}>
                  <ContactPage />
                </Suspense>
              }
            />
            <Route
              path="legal/:doc?"
              element={
                <Suspense fallback={<Cargando />}>
                  <LegalPage />
                </Suspense>
              }
            />
          </Route>
        </Routes>
      </BrowserRouter>
    </KlosetProvider>
  </StrictMode>,
);
