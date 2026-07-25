import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { FileUpload } from "@/components/ui/file-upload";
import { FormField, Selectish } from "@/components/ui/form-field";
import { Btn } from "@/components/ui/btn";
import { requiredDocs } from "@/data/mockDocuments";
import { useUploadDocument } from "@/hooks/queries";

export const Route = createFileRoute("/student/upload")({
  component: UploadPage,
});

function UploadPage() {
  const [type, setType] = useState(requiredDocs[0]);
  const [files, setFiles] = useState<File[]>([]);
  const [error, setError] = useState<string | null>(null);
  const upload = useUploadDocument();
  const navigate = useNavigate();

  function submit() {
    setError(null);
    const f = files[0];
    if (!f) { setError("Please choose a file first."); return; }
    upload.mutate(
      { type, filename: f.name },
      { onSuccess: () => navigate({ to: "/student/submissions" }), onError: (e) => setError(e instanceof Error ? e.message : "Upload failed.") },
    );
  }

  return (
    <div>
      <PageHeader title="Upload Requirements" description="Submit your TES documents for validation." />
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="lg:col-span-2 rounded-lg border bg-surface p-4 space-y-3">
          <FormField label="Document type" required>
            <Selectish value={type} onChange={(e) => setType(e.target.value)}>
              {requiredDocs.map((r) => <option key={r}>{r}</option>)}
            </Selectish>
          </FormField>
          <FormField label="File">
            <FileUpload multiple={false} onFiles={setFiles} />
          </FormField>
          {error && <p className="text-xs text-danger">{error}</p>}
          <div className="flex justify-end gap-2">
            <Btn variant="outline" onClick={() => navigate({ to: "/student/documents" })}>Cancel</Btn>
            <Btn variant="primary" onClick={submit} disabled={upload.isPending}>
              {upload.isPending ? "Uploading…" : "Submit for validation"}
            </Btn>
          </div>
        </div>
        <div className="rounded-lg border bg-surface p-4">
          <p className="text-sm font-semibold">Tips for accepted documents</p>
          <ul className="text-xs text-text-muted mt-2 space-y-1.5 list-disc list-inside">
            <li>Use original PDFs or clear photos (no edits).</li>
            <li>Keep file size under 10MB.</li>
            <li>Ensure all text is legible.</li>
            <li>For selfies with ID, hold the ID next to your face in good lighting.</li>
            <li>Edited or manipulated documents will be flagged as suspicious.</li>
          </ul>
        </div>
      </div>
    </div>
  );
}
