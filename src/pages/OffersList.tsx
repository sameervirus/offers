import { useState, useEffect, useMemo } from "react";
import { useAuth } from "../context/AuthContext";
import { Link, useNavigate } from "react-router-dom";
import styles from "../styles/OffersList.module.css";
import type { Offer } from "../types/offers";
import { getOffers, deleteOffer, getLastOfferNumber } from "../api/offers";
import React from "react";

const ITEMS_PER_PAGE = 10;

const SERVICE_TYPE_OPTIONS = [
  "Fabrication",
  "Erection",
  "Fabrication & Erection",
  "Survey",
];

const STATUS_OPTIONS = ["Pending", "Awarded", "Rejected"];

// Sentinel value for the "No Status" option (offers with an empty/null status).
const NO_STATUS = "__none__";

export default function OffersList() {
  const navigate = useNavigate();
  const [offers, setOffers] = useState<Offer[]>([]);
  const [pagination, setPagination] = useState<{
    total: number;
    total_pages: number;
  }>({
    total: 0,
    total_pages: 0,
  });
  const [searchTerm, setSearchTerm] = useState("");
  const [searchInput, setSearchInput] = useState("");
  const [serviceTypeFilter, setServiceTypeFilter] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [currentPage, setCurrentPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const { user, token, logout } = useAuth();

  useEffect(() => {
    const fetchOffers = async () => {
      if (!token) return;

      try {
        setLoading(true);
        setError(null);
        const res = await getOffers(
          currentPage,
          ITEMS_PER_PAGE,
          token,
          searchTerm,
          serviceTypeFilter,
          statusFilter
        );
        const offersWithParsedAttachments = res.data.map((offer: Offer) => ({
          ...offer,
          attachments:
            typeof offer.attachments === "string"
              ? JSON.parse(offer.attachments)
              : offer.attachments,
        }));
        setOffers(offersWithParsedAttachments);
        setPagination({
          total: res.pagination.total,
          total_pages: res.pagination.total_pages,
        });
      } catch (err) {
        console.error("Fetch failed:", err);
        setError("Failed to load offers.");
      } finally {
        setLoading(false);
      }
    };

    fetchOffers();
  }, [currentPage, token, searchTerm, serviceTypeFilter, statusFilter]);

  const handleDelete = async (id: number) => {
    if (!window.confirm("Are you sure?")) return;

    try {
      setLoading(true);
      await deleteOffer(id, token!);
      setOffers((prev) => prev.filter((o) => o.id !== id));
    } catch (err) {
      console.error(err);
      setError("Failed to delete offer.");
    } finally {
      setLoading(false);
    }
  };

  const filteredOffers = useMemo(() => {
    return offers.filter((offer) => {
      const matchesSearch = [offer.client, offer.project_name, offer.quo_no]
        .join(" ")
        .toLowerCase()
        .includes(searchTerm.toLowerCase());

      const matchesServiceType =
        !serviceTypeFilter || offer.work_type === serviceTypeFilter;

      const offerStatus = offer.status?.trim() || "";
      const matchesStatus =
        !statusFilter ||
        (statusFilter === NO_STATUS
          ? offerStatus === ""
          : offerStatus === statusFilter);

      return matchesSearch && matchesServiceType && matchesStatus;
    });
  }, [offers, searchTerm, serviceTypeFilter, statusFilter]);

  const handlePageChange = (page: number) => {
    if (page >= 1 && page <= pagination.total_pages) {
      setCurrentPage(page);
    }
  };

  const handleAssignNo = async (id: number, code: string) => {
    if (!token) return;

    try {
      const newQuoNo = await getLastOfferNumber(id, code, token);
      if (newQuoNo) {
        const today = new Date().toISOString().split("T")[0];

        setOffers((prevOffers) =>
          prevOffers.map((offer) =>
            offer.id === id
              ? { ...offer, quo_no: newQuoNo, quo_date: today }
              : offer
          )
        );

        console.log("Assigned quo_no:", newQuoNo);
      }
    } catch (error) {
      console.error("Failed to assign quote number:", error);
    }
  };

  if (!user) {
    navigate("/login");
    return null;
  }

  if (loading && offers.length === 0) {
    return <div className={styles.loading}>Loading offers...</div>;
  }

  if (error) {
    return <div className={styles.error}>{error}</div>;
  }

  return (
    <div className={styles.container}>
      <div className={styles.topBar}>
        <h1 className={styles.appTitle}>ARCONS Offers System</h1>
        <div className={styles.controls}>
          <div className={styles.searchContainer}>
            <input
              type="text"
              placeholder="Search offers..."
              className={styles.searchBox}
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter") {
                  setSearchTerm(searchInput.trim());
                  setCurrentPage(1);
                }
              }}
            />

            {searchInput.trim() && (
              <button
                onClick={() => {
                  setSearchInput("");
                  setSearchTerm("");
                  setCurrentPage(1);
                }}
                className={styles.clearButton}
                title="Clear"
              >
                ✕
              </button>
            )}

            <button
              onClick={() => {
                setSearchTerm(searchInput.trim());
                setCurrentPage(1);
              }}
              className={styles.searchButton}
              title="Search"
            >
              🔍
            </button>
          </div>

          <select
            className={styles.filterSelect}
            value={serviceTypeFilter}
            onChange={(e) => {
              setServiceTypeFilter(e.target.value);
              setCurrentPage(1);
            }}
            title="Filter by service type"
          >
            <option value="">All Service Types</option>
            {SERVICE_TYPE_OPTIONS.map((type) => (
              <option key={type} value={type}>
                {type}
              </option>
            ))}
          </select>

          <select
            className={styles.filterSelect}
            value={statusFilter}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setCurrentPage(1);
            }}
            title="Filter by status"
          >
            <option value="">All Statuses</option>
            {STATUS_OPTIONS.map((status) => (
              <option key={status} value={status}>
                {status}
              </option>
            ))}
            <option value={NO_STATUS}>No Status</option>
          </select>

          <Link to="/offers/new" className={styles.addButton}>
            Add New Offer
          </Link>
          <button
            onClick={() => window.print()}
            className={styles.printButton}
            title="Print list"
          >
            Print
          </button>
          <button onClick={logout} className={styles.logoutButton}>
            Logout
          </button>
        </div>
      </div>

      <div className={styles.tableContainer}>
        {loading && offers.length > 0 && (
          <div className={styles.loadingOverlay}>Updating data...</div>
        )}
        <table className={styles.offersTable}>
          <thead>
            <tr>
              <th style={{ minWidth: "106px" }}>Date</th>
              <th>Client</th>
              <th>Project</th>
              <th className={styles.scopeCol}>Scope of Work</th>
              <th>Service Type</th>
              <th>Quotation Date</th>
              <th>Quotation No#</th>
              <th>Quotation Files</th>
              <th>Status</th>
              <th className={styles.actionsCol}>Actions</th>
            </tr>
          </thead>
          <tbody>
            {filteredOffers.map((offer) => (
              <tr
                key={offer.id}
                className={styles[offer.status?.trim().toLowerCase() || ""]}
              >
                <td>{offer.rec_date}</td>
                <td>{offer.client}</td>
                <td>{offer.project_name}</td>
                <td className={styles.scopeCol}>{offer.description}</td>
                <td>{offer.work_type}</td>
                <td>
                  {offer.quo_date === "0000-00-00" ? null : offer.quo_date}
                </td>
                <td>
                  {offer.quo_no ? (
                    offer.quo_no
                  ) : (
                    <button
                      className={styles.printHide}
                      onClick={() =>
                        handleAssignNo(
                          offer.id,
                          offer.work_type === "Erection" ? "QU" : "FQU"
                        )
                      }
                    >
                      Assign no.
                    </button>
                  )}
                </td>
                <td>
                  {offer.attachments?.map((file) => (
                    <a
                      href={`https://offers.arconsegypt.com/uploads/${offer.id}/${file}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      key={file}
                    >
                      📄 {file}
                    </a>
                  )) || "—"}
                </td>
                <td>
                  <span
                    className={`${styles.status} ${
                      styles[offer.status?.trim().toLowerCase() || ""]
                    }`}
                  >
                    {offer.status}
                  </span>
                </td>
                <td className={styles.actionsCol}>
                  <Link
                    to={`/offers/${offer.id}`}
                    className={styles.actionButton}
                  >
                    Edit
                  </Link>
                  <button
                    onClick={() => handleDelete(offer.id!)}
                    className={styles.deleteButton}
                    style={{ display: "none" }}
                  >
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {filteredOffers.length === 0 && !loading && (
          <div className={styles.noResults}>
            No offers found{searchTerm ? ` matching "${searchTerm}"` : ""}
          </div>
        )}
      </div>

      {pagination.total_pages > 1 && (
        <div className={styles.pagination}>
          {/* Previous button */}
          <button
            className={`${styles.paginationButton} ${
              currentPage === 1 ? styles.disabled : ""
            }`}
            onClick={() => handlePageChange(currentPage - 1)}
            disabled={currentPage === 1}
          >
            &lt;
          </button>

          {Array.from({ length: pagination.total_pages }, (_, i) => i + 1)
            .filter(
              (page) =>
                // Show 2 pages before and after current page
                page === 1 ||
                page === pagination.total_pages ||
                (page >= currentPage - 2 && page <= currentPage + 2)
            )
            .map((page, index, array) => {
              const prevPage = array[index - 1];

              // Show "..." between gaps
              if (prevPage && page - prevPage > 1) {
                return (
                  <React.Fragment key={page}>
                    <span className={styles.paginationEllipsis}>...</span>
                    <button
                      className={`${styles.paginationButton} ${
                        currentPage === page ? styles.active : ""
                      }`}
                      onClick={() => handlePageChange(page)}
                    >
                      {page}
                    </button>
                  </React.Fragment>
                );
              }

              return (
                <button
                  key={page}
                  className={`${styles.paginationButton} ${
                    currentPage === page ? styles.active : ""
                  }`}
                  onClick={() => handlePageChange(page)}
                >
                  {page}
                </button>
              );
            })}

          {/* Next button */}
          <button
            className={`${styles.paginationButton} ${
              currentPage === pagination.total_pages ? styles.disabled : ""
            }`}
            onClick={() => handlePageChange(currentPage + 1)}
            disabled={currentPage === pagination.total_pages}
          >
            &gt;
          </button>
        </div>
      )}

      <div className={styles.printFooter} dir="rtl">
        <span>دليل الجودة الخاص بشركة أركونز</span>
        <span>إصدار رقم (2)</span>
        <span>نموذج رقم F-22-03</span>
        <span>1/11/2025</span>
      </div>
    </div>
  );
}
