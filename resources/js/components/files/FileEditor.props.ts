export interface FileEditorProps {
    filePath: string;
    content: string;
    isDirty: boolean;
    isSaving: boolean;
    onContentChange: (content: string) => void;
    onSave: () => void;
    onClose: () => void;
}
