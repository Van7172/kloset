import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { AppLayout } from './components/AppLayout';
import { KlosetProvider } from './store/KlosetContext';
import { CatalogPage } from './pages/CatalogPage';
import { ProductPage } from './pages/ProductPage';
import { MeasurePage } from './pages/MeasurePage';
import { ResultPage } from './pages/ResultPage';
import { AvatarPage } from './pages/AvatarPage';
import { CartPage } from './pages/CartPage';
import { CheckoutPage } from './pages/CheckoutPage';
import { OrdersPage } from './pages/OrdersPage';
import { AuthPage } from './pages/AuthPage';
import { AccountPage } from './pages/AccountPage';
import './index.css';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <KlosetProvider>
      <BrowserRouter>
        <Routes>
          <Route element={<AppLayout />}>
            <Route index element={<CatalogPage />} />
            <Route path="producto/:url" element={<ProductPage />} />
            <Route path="medidas" element={<MeasurePage />} />
            <Route path="resultado" element={<ResultPage />} />
            <Route path="avatar" element={<AvatarPage />} />
            <Route path="bolsa" element={<CartPage />} />
            <Route path="pago" element={<CheckoutPage />} />
            <Route path="pedidos" element={<OrdersPage />} />
            <Route path="entrar" element={<AuthPage />} />
            <Route path="cuenta" element={<AccountPage />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </KlosetProvider>
  </StrictMode>,
);
