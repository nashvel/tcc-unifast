import { useRef, useState, type DragEvent } from "react";
import { IconUpload, IconFile, IconX } from "@tabler/icons-react";
import { cn } from "@/lib/utils";

interface Props {
  multiple?: boolean;
  hint?: string;
  onFiles?: (files: File[]) => void;
}

export function FileUpload({ multiple = true, hint = "PDF, JPG, PNG up to 10MB", onFiles }: Props) {
  const [files, setFiles] = useState<File[]>([]);
  const [drag, setDrag] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  function handleFiles(list: FileList | null) {
    if (!list) return;
    const arr = Array.from(list);
    setFiles((prev) => (multiple ? [...prev, ...arr] : arr));
    onFiles?.(arr);
  }

  function onDrop(e: DragEvent) {
    e.preventDefault();
    setDrag(false);
    handleFiles(e.dataTransfer.files);
  }

  return (
    <div>
      <div
        onDragOver={(e) => { e.preventDefault(); setDrag(true); }}
        onDragLeave={() => setDrag(false)}
        onDrop={onDrop}
        onClick={() => inputRef.current?.click()}
        className={cn(
          "flex flex-col items-center justify-center gap-1 rounded-lg border border-dashed bg-surface px-6 py-8 cursor-pointer transition-colors",
          drag ? "border-primary bg-primary-soft/30" : "hover:bg-surface-muted/40",
        )}
      >
        <IconUpload size={20} className="text-text-muted" />
        <p className="text-sm font-medium">Drag & drop or click to upload</p>
        <p className="text-xs text-text-soft">{hint}</p>
        <input ref={inputRef} type="file" multiple={multiple} className="hidden" onChange={(e) => handleFiles(e.target.files)} />
      </div>
      {files.length > 0 && (
        <ul className="mt-3 space-y-1.5">
          {files.map((f, i) => (
            <li key={i} className="flex items-center justify-between rounded-md border bg-surface px-2.5 py-1.5 text-xs">
              <span className="flex items-center gap-2 truncate">
                <IconFile size={14} className="text-text-muted" />
                <span className="truncate">{f.name}</span>
                <span className="text-text-soft">({(f.size / 1024).toFixed(1)} KB)</span>
              </span>
              <button onClick={(e) => { e.stopPropagation(); setFiles(files.filter((_, j) => j !== i)); }} className="text-text-soft hover:text-danger">
                <IconX size={14} />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
