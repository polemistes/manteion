<script setup lang="ts">
import {
    Bell,
    CalendarPlus,
    Check,
    Ellipsis,
    Flag,
    Lock,
    Map,
    MapPin,
    Plus,
    SlidersHorizontal,
    Trash,
    UserPlus,
    Users,
} from '@lucide/vue';
import { ref } from 'vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import Avatar from '@/components/ui/Avatar.vue';
import AvatarStack from '@/components/ui/AvatarStack.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import CountBadge from '@/components/ui/CountBadge.vue';
import DateBadge from '@/components/ui/DateBadge.vue';
import DateLeaf from '@/components/ui/DateLeaf.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FilterChip from '@/components/ui/FilterChip.vue';
import IconButton from '@/components/ui/IconButton.vue';
import ListRow from '@/components/ui/ListRow.vue';
import Menu from '@/components/ui/Menu.vue';
import MenuItem from '@/components/ui/MenuItem.vue';
import MenuSeparator from '@/components/ui/MenuSeparator.vue';
import OptionCards from '@/components/ui/OptionCards.vue';
import SearchField from '@/components/ui/SearchField.vue';
import SectionHeader from '@/components/ui/SectionHeader.vue';
import SegmentedControl from '@/components/ui/SegmentedControl.vue';
import SelectField from '@/components/ui/SelectField.vue';
import Skeleton from '@/components/ui/Skeleton.vue';
import SwitchRow from '@/components/ui/SwitchRow.vue';
import Tag from '@/components/ui/Tag.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextField from '@/components/ui/TextField.vue';
import { toast } from '@/lib/toast';

/** Every primitive in its states, for checking the design system. */
const people = [
    { id: 1, name: 'Alice Nilsen', avatar_url: null },
    { id: 2, name: 'Bjørn Haugen', avatar_url: null },
    { id: 3, name: 'Camilla Iversen', avatar_url: null },
    { id: 4, name: 'Erik Solberg', avatar_url: null },
    { id: 5, name: 'Frida Berg', avatar_url: null },
];

const today = new Date().toISOString();
const later = new Date(Date.now() + 5 * 86_400_000).toISOString();

const chips = ref({ all: true, invites: false, going: false, music: true });
const switches = ref({ direct: true, group: true, invites: false });
const checked = ref(true);
const view = ref('going');
const visibility = ref('everyone');
const select = ref('everyone');
const search = ref('board games');
const name = ref('');
const notes = ref('Bring a game if you have one.');
const dialogOpen = ref(false);

function showToast(): void {
    toast({
        title: "You're going.",
        description: 'Bjørn can see your answer.',
        action: { label: 'Undo', onClick: () => undefined },
    });
}
</script>

<template>
    <PageHeader
        title="Design kit"
        kicker="Manteion · Terracotta"
        lede="Every primitive in its states. Switch the system theme to check dark mode."
        root
        width="wide"
    >
        <template #phone-actions>
            <IconButton :icon="Plus" label="Create" variant="create" />
        </template>
        <template #actions>
            <Button :icon="Plus" variant="primary">Create event</Button>
        </template>
    </PageHeader>

    <PageContainer width="wide" class="pb-16">
        <SectionHeader title="Buttons" />
        <div class="flex flex-wrap items-center gap-3">
            <Button variant="primary" :icon="Plus">Create event</Button>
            <Button :icon="UserPlus">Invite</Button>
            <Button variant="ghost">Cancel</Button>
            <Button variant="danger" :icon="Trash">Delete event</Button>
            <Button size="sm">Accept</Button>
            <Button size="lg" variant="primary">Opprett arrangement</Button>
            <Button disabled>Disabled</Button>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <IconButton :icon="Bell" label="Activity" :badge="5" />
            <IconButton :icon="Ellipsis" label="More" variant="muted" />
            <IconButton :icon="Plus" label="Create" variant="create" />
            <IconButton :icon="Flag" label="Report" size="sm" />
            <span class="rounded-md bg-surface-3 p-2">
                <IconButton :icon="MapPin" label="Map" variant="floating" />
            </span>
        </div>

        <SectionHeader title="Badges, tags, chips" :count="3" />
        <div class="flex flex-wrap items-center gap-2">
            <CountBadge :count="3" />
            <CountBadge :count="128" />
            <CountBadge :count="12" tone="neutral" />
            <Tag>Games</Tag>
            <Tag :icon="Lock">Private</Tag>
            <Tag tone="success">This device</Tag>
            <Tag tone="host">Host</Tag>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <FilterChip v-model:pressed="chips.all">All</FilterChip>
            <FilterChip v-model:pressed="chips.invites" :badge="1"
                >Invitations</FilterChip
            >
            <FilterChip v-model:pressed="chips.going">Going</FilterChip>
            <FilterChip :icon="SlidersHorizontal" :count="2"
                >Filters</FilterChip
            >
            <FilterChip v-model:pressed="chips.music" small>Music</FilterChip>
            <FilterChip small>Outdoors</FilterChip>
        </div>

        <SectionHeader title="Avatars" />
        <div class="flex flex-wrap items-center gap-3">
            <Avatar name="Alice Nilsen" :size="64" />
            <Avatar name="Bjørn Haugen" :size="48" />
            <Avatar name="Camilla" :size="40" />
            <Avatar name="Erik Solberg" :size="28" />
            <AvatarStack :people="people" :total="11" />
        </div>

        <SectionHeader title="Fields" />
        <div class="grid max-w-panel gap-4">
            <TextField v-model="name" label="Event name" headline required />
            <TextField
                label="Place"
                :icon="MapPin"
                hint="An address or a place name."
                optional
            />
            <TextField label="Email" error="This email address is not valid." />
            <TextArea v-model="notes" label="Description" />
            <SearchField v-model="search" label="Search events" />
            <SelectField
                v-model="select"
                label="Who can see my answer"
                :options="[
                    {
                        value: 'everyone',
                        label: 'Everyone invited or following',
                    },
                    { value: 'friends', label: 'Only my friends' },
                    { value: 'none', label: 'No one except the host' },
                ]"
            />
        </div>

        <SectionHeader title="Choices" />
        <div class="grid max-w-panel gap-4">
            <SegmentedControl
                v-model="view"
                label="Guests"
                :segments="[
                    { value: 'going', label: 'Going', count: 8 },
                    { value: 'maybe', label: 'Maybe', count: 2 },
                    { value: 'invited', label: 'Invited', count: 6 },
                ]"
            />
            <OptionCards
                v-model="visibility"
                label="Who can see your answer"
                :options="[
                    {
                        value: 'everyone',
                        title: 'Everyone invited or following',
                        description: 'Guests, and people who follow Bjørn',
                        icon: Users,
                    },
                    {
                        value: 'friends',
                        title: 'Only my friends',
                        description: 'Your friends among the guests',
                    },
                ]"
            />
            <label class="flex items-center gap-3">
                <Checkbox v-model="checked" />
                <span>Ingrid Dahl</span>
            </label>
            <Card>
                <SwitchRow
                    v-model="switches.direct"
                    title="Direct messages"
                    description="When someone writes to you"
                />
                <SwitchRow
                    v-model="switches.group"
                    title="Group messages"
                    description="New messages in your group chats"
                />
                <SwitchRow
                    v-model="switches.invites"
                    title="Invitations"
                    description="When someone invites you to an event"
                />
            </Card>
        </div>

        <SectionHeader title="Dates" meta="Sunday 4 October" />
        <div class="flex flex-wrap items-start gap-3">
            <DateBadge :date="today" />
            <DateBadge :date="later" />
            <DateBadge :date="later" size="sm" />
            <DateBadge :date="later" size="lg" />
            <DateLeaf :date="later" :end="later" class="w-60" />
        </div>

        <SectionHeader title="Lists" />
        <Card class="max-w-panel">
            <ListRow
                as="a"
                href="#"
                interactive
                title="Camilla Iversen"
                sub="3 mutual friends · 1 h"
                :inset="74"
            >
                <template #leading>
                    <Avatar name="Camilla Iversen" :size="44" />
                </template>
                <template #trailing>
                    <Button size="sm" variant="primary" class="relative z-10"
                        >Accept</Button
                    >
                </template>
            </ListRow>
            <ListRow
                title="Open map"
                sub="Kvitfjellvegen 590, Ringebu"
                :inset="70"
            >
                <template #leading>
                    <span
                        class="grid size-10 place-items-center rounded-sm bg-surface-2 text-ink-muted"
                    >
                        <Map :size="20" aria-hidden="true" />
                    </span>
                </template>
                <template #trailing>
                    <Menu>
                        <template #trigger>
                            <IconButton
                                :icon="Ellipsis"
                                label="More"
                                variant="muted"
                            />
                        </template>
                        <MenuItem :icon="CalendarPlus"
                            >Add to calendar</MenuItem
                        >
                        <MenuItem :icon="Check" hint="1 muted"
                            >Mute categories…</MenuItem
                        >
                        <MenuSeparator />
                        <MenuItem :icon="Trash" danger>Delete</MenuItem>
                    </Menu>
                </template>
            </ListRow>
        </Card>

        <SectionHeader title="Overlays" />
        <div class="flex flex-wrap gap-3">
            <Button @click="dialogOpen = true">Open dialog</Button>
            <Button @click="showToast">Show toast</Button>
        </div>
        <AppDialog
            v-model:open="dialogOpen"
            title="Who can see your answer?"
            description="Bjørn, the host, always can."
        >
            <OptionCards
                v-model="visibility"
                :options="[
                    {
                        value: 'everyone',
                        title: 'Everyone invited or following',
                    },
                    { value: 'friends', title: 'Only my friends' },
                    { value: 'none', title: 'No one except the host' },
                ]"
            />
            <template #actions>
                <Button variant="primary" @click="dialogOpen = false"
                    >Done</Button
                >
                <Button variant="ghost" @click="dialogOpen = false"
                    >Cancel</Button
                >
            </template>
        </AppDialog>

        <SectionHeader title="Empty and loading" />
        <div class="grid gap-4 sm:grid-cols-2">
            <Card>
                <EmptyState
                    :icon="CalendarPlus"
                    title="Nothing planned yet"
                    body="When friends invite you, or people you follow publish something, it shows up here."
                >
                    <Button>Create event</Button>
                </EmptyState>
            </Card>
            <Card class="p-4">
                <Skeleton class="aspect-video rounded-md" />
                <Skeleton class="mt-3 h-4 w-2/5 rounded-xs" />
                <Skeleton class="mt-2 h-5 w-4/5 rounded-xs" />
                <Skeleton class="mt-2 h-4 w-3/5 rounded-xs" />
            </Card>
        </div>
    </PageContainer>
</template>
