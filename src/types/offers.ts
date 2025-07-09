export interface Offer {
  id: number;
  rec_date: string | null;
  client: string;
  project_name: string | null;
  description: string | null;
  work_type: string;
  quo_date: string | null;
  quo_values: string | null;
  quo_no: string | null;
  status: string | null;
  attachments: string[] | null;
}
