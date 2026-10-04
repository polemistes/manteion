import { markRaw, reactive } from 'vue';
import type { Component } from 'vue';

export interface ToastAction {
    label: string;
    onClick: () => void;
}

export interface ToastOptions {
    title: string;
    description?: string;
    icon?: Component;
    tone?: 'status' | 'error';
    action?: ToastAction;
    /** Milliseconds on screen; 5 s by default. */
    duration?: number;
}

export interface Toast extends ToastOptions {
    id: number;
}

export interface MessageBanner {
    id: number;
    conversationId: number;
    name: string;
    avatarUrl: string | null;
    preview: string;
    href: string;
}

/**
 * Status toasts ("You're going. Bjørn can see your answer." · Undo) and
 * message banners (someone wrote to you). Only ever filled in the browser,
 * in response to something that happened there, so the server never sees
 * an entry.
 */
export const toastState = reactive<{
    toasts: Toast[];
    banners: MessageBanner[];
}>({
    toasts: [],
    banners: [],
});

let nextId = 0;

/** Shows a status toast. Toasts appear one at a time; later ones wait. */
export function toast(options: ToastOptions): number {
    const id = ++nextId;

    toastState.toasts.push({
        ...options,
        icon: options.icon ? markRaw(options.icon) : undefined,
        id,
    });

    return id;
}

/** Shorthand for an error toast (`role="alert"`). */
export function toastError(title: string, description?: string): number {
    return toast({ title, description, tone: 'error' });
}

export function dismissToast(id: number): void {
    const index = toastState.toasts.findIndex((item) => item.id === id);

    if (index !== -1) {
        toastState.toasts.splice(index, 1);
    }
}

/**
 * Shows a banner for an incoming message. A newer message from the same
 * conversation replaces the older banner instead of stacking.
 */
export function showMessageBanner(banner: Omit<MessageBanner, 'id'>): void {
    const existing = toastState.banners.findIndex(
        (item) => item.conversationId === banner.conversationId,
    );

    if (existing !== -1) {
        toastState.banners.splice(existing, 1);
    }

    toastState.banners.push({ ...banner, id: ++nextId });

    if (toastState.banners.length > 3) {
        toastState.banners.shift();
    }
}

export function dismissBanner(id: number): void {
    const index = toastState.banners.findIndex((item) => item.id === id);

    if (index !== -1) {
        toastState.banners.splice(index, 1);
    }
}
