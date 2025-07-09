import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import ProtectedRoute from "./components/ProtectedRoute";
import Login from "./pages/Login";
import OffersList from "./pages/OffersList";
import OfferForm from "./pages/OfferForm";

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route element={<ProtectedRoute />}>
          <Route path="/offers" element={<OffersList />} />
          <Route path="/offers/new" element={<OfferForm />} />
          <Route path="/offers/:id" element={<OfferForm />} />
        </Route>
        <Route path="*" element={<Navigate to="/login" replace />} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;
