// api/offers.ts
import type { Offer } from "../types/offers";
import type { Member } from "../types/members";

const API_URL = "https://offers.arconsegypt.com/api";

interface PaginatedResponse {
  data: Offer[];
  pagination: {
    total: number;
    page: number;
    limit: number;
    total_pages: number;
  };
}

interface AuthResponse {
  status: boolean;
  user?: Member;
  error?: string;
}

export async function loginApi(
  username: string,
  password: string
): Promise<AuthResponse> {
  try {
    const response = await fetch(`${API_URL}/login`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        action: "login",
        username,
        password,
      }),
    });

    if (!response.ok) {
      const errorData = await response.json();
      throw new Error(errorData.error || "Login failed");
    }

    return await response.json();
  } catch (error) {
    console.error("Login error:", error);
    return {
      status: false,
      error: error instanceof Error ? error.message : "Unknown error occurred",
    };
  }
}

export async function getOffers(
  page: number,
  limit: number,
  token: string,
  searchTerm?: string
): Promise<PaginatedResponse> {
  const url = new URL(`${API_URL}/offers`);
  url.searchParams.append("page", String(page));
  url.searchParams.append("limit", String(limit));
  if (searchTerm) {
    url.searchParams.append("search", searchTerm);
  }

  const response = await fetch(url.toString(), {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  });

  if (!response.ok) {
    throw new Error("Failed to fetch offers");
  }

  return response.json();
}

export async function getOffer(id: number, token: string): Promise<Offer> {
  try {
    const response = await fetch(`${API_URL}/offers/${id}`, {
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
    });

    if (!response.ok) throw new Error("Failed to fetch offer");
    const data = await response.json();
    return data.data;
  } catch (error) {
    console.error("Error fetching offer:", error);
    throw error;
  }
}

export async function saveOffer(
  offer: Partial<Offer>,
  token: string,
  files: FileList | null
): Promise<Offer> {
  try {
    const isEdit = !!offer.id;
    const url = isEdit ? `${API_URL}/offers/${offer.id}` : `${API_URL}/offers`;

    const method = isEdit ? "POST" : "POST";

    const formData = new FormData();
    formData.append("offer", JSON.stringify(offer));
    if (files) {
      Array.from(files).forEach((file) => {
        formData.append("files[]", file);
      });
    }

    const response = await fetch(url, {
      method,
      headers: {
        Authorization: `Bearer ${token}`,
      },
      body: formData,
    });

    const data = await response.json();

    if (!response.ok || !data.status) {
      throw new Error(data.error || "Failed to save offer");
    }

    return data.data;
  } catch (error) {
    console.error("Error saving offer:", error);
    throw error;
  }
}

export async function deleteOffer(id: number, token: string): Promise<void> {
  try {
    const response = await fetch(`${API_URL}/offers`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({
        action: "delete",
        id,
      }),
    });

    if (!response.ok) throw new Error("Failed to delete offer");
  } catch (error) {
    console.error("Error deleting offer:", error);
    throw error;
  }
}

export async function getLastOfferNumber(
  id: number,
  code: string,
  token: string
): Promise<string> {
  try {
    const response = await fetch(`${API_URL}/offers`, {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({
        id,
        code,
      }),
    });

    if (!response.ok) throw new Error("Failed to get last offer number");
    const data = await response.json();
    return data.quo_no;
  } catch (error) {
    console.error("Error getting last offer number:", error);
    throw error;
  }
}

// Add this function to validate tokens
export async function validateToken(token: string): Promise<boolean> {
  try {
    const response = await fetch(`${API_URL}/validate`, {
      method: "GET",
      headers: {
        Authorization: `Bearer ${token}`,
      },
    });
    return response.ok;
  } catch (error) {
    console.error("Token validation error:", error);
    return false;
  }
}
