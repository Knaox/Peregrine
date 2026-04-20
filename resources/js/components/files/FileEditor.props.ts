export interface FileEditorProps {
    filePath: string;
    content: string;
    isDirty: boolean;
    isSaving: boolean;
    onContentChange: (content: string) => void;
    onSave: () => void;
    onClose: () => void;
    /** When false the textarea becomes read-only and the Save button is hidden. */
    canEdit?: boolean;
}
