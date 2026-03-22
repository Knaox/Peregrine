export interface ConsoleInputProps {
    onSend: (command: string) => void;
    onHistoryUp: () => string;
    onHistoryDown: () => string;
    disabled: boolean;
}
