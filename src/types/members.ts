export interface Member {
  id: number;
  username: string;
  email: string;
  password: string;
  status: string;
  admin: boolean;
  token: string | null;
}
