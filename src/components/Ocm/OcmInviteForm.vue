<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="contact-header__infos">
		<h5>{{ t('contacts', 'Invite someone outside your organisation to collaborate.') }}</h5>
		<p>{{ t('contacts', 'After the invitee has accepted the invite both of you will appear in each others\' contacts list and you can start sharing data with each other.') }}</p>
		
		<div class="form-field">
			<NcTextField type="text"
				:label="t('contacts', 'Invite label (for your reference)')"
				:placeholder="t('contacts', 'e.g. Mahdi from OCM')"
				:value="ocmInvite.note"
				data-testid="ocm-invite-note-input"
				@input="setNote" />
			<p class="hint">{{ t('contacts', 'A name or note to help you identify this invite') }}</p>
		</div>

		<div class="email-section">
			<label class="email-toggle">
				<input type="checkbox" v-model="sendEmail" data-testid="ocm-invite-send-email-checkbox">
				<span>{{ t('contacts', 'Send invite via email') }}</span>
			</label>
			<div v-if="sendEmail" class="email-fields">
				<NcTextField
					type="email"
					:label="t('contacts', 'Recipient email')"
					:placeholder="t('contacts', 'email@example.com')"
					:value="ocmInvite.email"
					inputmode="email"
					data-testid="ocm-invite-email-input"
					@input="setEmail" />
				<NcTextField
					:label="t('contacts', 'Personal message (optional)')"
					:placeholder="t('contacts', 'Message to include in the email')"
					:value="ocmInvite.message"
					data-testid="ocm-invite-message-input"
					@input="setMessage" />
			</div>
			<p v-else class="hint">{{ t('contacts', 'Without email, you will need to share the invite link manually.') }}</p>
		</div>

		<div class="actions">
			<slot name="new-invite-actions" />
		</div>
	</div>
</template>

<script>
import { NcTextField } from '@nextcloud/vue'

export default {
	name: 'OcmInviteForm',
	components: {
		NcTextField,
	},
	props: {
		ocmInvite: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			sendEmail: false,
		}
	},
	watch: {
		sendEmail(newVal) {
			if (!newVal) {
				this.ocmInvite.email = ''
				this.ocmInvite.message = ''
			}
		},
	},
	methods: {
		setNote(e) {
			this.ocmInvite.note = e.target.value
		},
		setEmail(e) {
			this.ocmInvite.email = e.target.value
		},
		setMessage(e) {
			this.ocmInvite.message = e.target.value
		},
	},
}
</script>
<style lang="scss" scoped>
.contact-header__infos {
	margin: 1em;

	h5 {
		margin: 0 0 0.5em 0;
	}

	> p {
		margin-bottom: 1.5em;
		color: var(--color-text-maxcontrast);
	}

	.hint {
		font-size: 0.85em;
		color: var(--color-text-maxcontrast);
		margin-top: 0.5em;
		margin-bottom: 0;
	}

	.form-field {
		margin-bottom: 1.5em;
	}

	.email-section {
		padding: 1em;
		background: var(--color-background-dark);
		border-radius: var(--border-radius-large);
		margin-bottom: 1.5em;

		.email-toggle {
			display: flex;
			align-items: center;
			gap: 0.5em;
			cursor: pointer;
			user-select: none;
			margin-bottom: 0.5em;

			input[type="checkbox"] {
				width: 18px;
				height: 18px;
				cursor: pointer;
				accent-color: var(--color-primary);
				outline: none;
				box-shadow: none;

				&:focus,
				&:hover,
				&:focus-visible {
					outline: none;
					box-shadow: none;
				}
			}

			span {
				font-weight: 500;
			}
		}

		.email-fields {
			display: flex;
			flex-direction: column;
			gap: 1em;
			margin-top: 1em;
		}

		.hint {
			margin-top: 0.5em;
		}
	}

	.actions {
		margin-top: 1em;
	}
}
</style>