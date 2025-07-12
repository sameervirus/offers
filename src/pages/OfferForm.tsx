// src/pages/OfferForm.tsx
import { useState, useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";
import styles from "../styles/OfferForm.module.css";
import type { Offer } from "../types/offers";
import { getOffer, saveOffer } from "../api/offers";
import { useAuth } from "../context/AuthContext";

const WORK_SCOPE_OPTIONS = [
  "Fabrication",
  "Erection",
  "Fabrication & Erection",
  "Survey",
];

const STATUS_OPTIONS = ["Pending", "Awarded", "Rejected"];

export default function OfferForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { token } = useAuth();
  const [offer, setOffer] = useState<Partial<Offer>>({
    client: "",
    project_name: "",
    description: "",
    work_type: "",
    status: "Pending",
  });

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [files, setFiles] = useState<FileList | null>(null);
  const [documents, setDocuments] = useState<string[]>([]);

  useEffect(() => {
    if (id && token) {
      const fetchOffer = async () => {
        try {
          const response = await getOffer(Number(id), token);
          setOffer(response);
          if (typeof response.attachments === "string") {
            setDocuments(JSON.parse(response.attachments) ?? []);
          } else if (Array.isArray(response.attachments)) {
            setDocuments(response.attachments);
          } else {
            setDocuments([]);
          }
        } catch (err) {
          console.error("Failed to fetch offers:", err);
        }
      };
      fetchOffer();
    }
  }, [id, token]);

  const handleChange = (
    e: React.ChangeEvent<
      HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement
    >
  ) => {
    const { name, value } = e.target;
    setOffer((prev) => ({ ...prev, [name]: value }));
  };

  const handleDeleteDocument = (file: string) => {
    if (file && confirm("Are you sure you want delete this file?")) {
      setDocuments((docs) => docs.filter((doc) => doc !== file));
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) {
      navigate("/login");
      return;
    }

    const updatedOffer = { ...offer, documents };

    try {
      setLoading(true);
      await saveOffer(updatedOffer, token, files);
      alert("Saved successfully");
      navigate("/offers");
    } catch (err) {
      console.error("Failed to save offer:", err);
      setError("Failed to save offer.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className={styles.formContainer}>
      <div className={styles.formHeader}>
        <h1 className={styles.formTitle}>
          {id ? "Edit Offer" : "Add New Offer"}
        </h1>
      </div>

      {error && <p className="error">{error}</p>}
      <form onSubmit={handleSubmit} className={styles.formGrid}>
        {/* Row 1: Date (col3) + Client (col9) */}
        <div className={`${styles.formGroup} ${styles.col3}`}>
          <label htmlFor="rec_date">Date</label>
          <input
            id="rec_date"
            name="rec_date"
            type="date"
            value={offer.rec_date || ""}
            onChange={handleChange}
          />
        </div>

        <div className={`${styles.formGroup} ${styles.col9}`}>
          <label htmlFor="client">Client *</label>
          <input
            id="client"
            name="client"
            type="text"
            value={offer.client}
            onChange={handleChange}
            required
          />
        </div>

        {/* Row 2: Project (col12) */}
        <div className={`${styles.formGroup} ${styles.col12}`}>
          <label htmlFor="project_name">Project Name *</label>
          <input
            id="project_name"
            name="project_name"
            type="text"
            value={offer.project_name || ""}
            onChange={handleChange}
            required
          />
        </div>

        {/* Row 3: Description (col12) */}
        <div className={`${styles.formGroup} ${styles.col12}`}>
          <label htmlFor="description">
            Description <span className={styles.optionalBadge}>(optional)</span>
          </label>
          <textarea
            id="description"
            name="description"
            value={offer.description || ""}
            onChange={handleChange}
          />
        </div>

        {/* Row 4: Work Scope (col6) + Status (col6) */}
        <div className={`${styles.formGroup} ${styles.col6}`}>
          <label htmlFor="work_type">Work Scope *</label>
          <select
            id="work_type"
            name="work_type"
            value={offer.work_type || ""}
            onChange={handleChange}
            required
          >
            <option value="">Select work scope</option>
            {WORK_SCOPE_OPTIONS.map((scope) => (
              <option key={scope} value={scope}>
                {scope}
              </option>
            ))}
          </select>
        </div>

        <div className={`${styles.formGroup} ${styles.col6}`}>
          <label htmlFor="status">
            Status <span className={styles.optionalBadge}>(optional)</span>
          </label>
          <select
            id="status"
            name="status"
            value={offer.status || ""}
            onChange={handleChange}
          >
            {STATUS_OPTIONS.map((status) => (
              <option key={status} value={status}>
                {status}
              </option>
            ))}
          </select>
        </div>

        {/* Row 5: Offer No. (col4) + Offer Date (col4) + Offer Value (col4) */}
        <div className={`${styles.formGroup} ${styles.col4}`}>
          <label htmlFor="quo_no">
            Offer No. <span className={styles.optionalBadge}>(optional)</span>
          </label>
          <input
            id="quo_no"
            name="quo_no"
            type="text"
            value={offer.quo_no || ""}
            onChange={handleChange}
          />
        </div>

        <div className={`${styles.formGroup} ${styles.col4}`}>
          <label htmlFor="quo_date">
            Offer Date <span className={styles.optionalBadge}>(optional)</span>
          </label>
          <input
            id="quo_date"
            name="quo_date"
            type="date"
            value={offer.quo_date || ""}
            onChange={handleChange}
          />
        </div>

        <div className={`${styles.formGroup} ${styles.col4}`}>
          <label htmlFor="quo_values">
            Offer Value <span className={styles.optionalBadge}>(optional)</span>
          </label>
          <input
            id="quo_values"
            name="quo_values"
            type="text"
            value={offer.quo_values || ""}
            onChange={handleChange}
          />
        </div>

        <div className={`${styles.formGroup} ${styles.col12}`}>
          <label>
            Attach Files:
            <input
              type="file"
              multiple
              onChange={(e) => setFiles(e.target.files)}
              accept=".pdf"
            />
          </label>
        </div>

        {documents && documents.length > 0 && (
          <div className={`${styles.formGroup} ${styles.col12}`}>
            <ul className={styles.documentList}>
              {documents?.map((file) => (
                <li key={file} className={styles.documentItem}>
                  <a
                    href={`https://offers.arconsegypt.com/uploads/${offer.id}/${file}`}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    {file}
                  </a>
                  <button
                    type="button"
                    className={styles.deleteDocButton}
                    onClick={() => handleDeleteDocument(file)}
                    aria-label={`Delete ${file}`}
                  >
                    ×
                  </button>
                </li>
              ))}
            </ul>
          </div>
        )}

        {/* Action Buttons */}
        <div className={styles.buttonGroup}>
          <button
            type="button"
            className={styles.cancelButton}
            onClick={() => navigate("/offers")}
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={loading}
            className={styles.submitButton}
          >
            {loading ? "Saving..." : id ? "Update Offer" : "Create Offer"}
          </button>
        </div>
      </form>
    </div>
  );
}
