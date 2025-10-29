/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { MemberLevel, MemberType } from './constants.ts'

import logger from '../services/logger.js'
import Circle from './circle.ts'
import { MemberLevels, MemberTypes } from './constants.ts'

export default class Member {
	_data: any = {}
	_circle: Circle

	/**
	 * Creates an instance of Member
	 *
	 * @param data
	 * @param circle
	 */
	constructor(data: any, circle: Circle) {
		if (typeof data !== 'object') {
			throw new Error('Invalid member')
		}

		// if no uid set, fail
		if (data.id && typeof data.id !== 'string') {
			logger.error('This member do not have a proper uid', data)
			throw new Error('This member do not have a proper uid')
		}

		this._circle = circle
		this._data = data
	}

	/**
	 * Get the circle of this member
	 */
	get circle(): Circle {
		return this._circle
	}

	/**
	 * Set the circle of this member
	 */
	set circle(circle: Circle) {
		if (circle.constructor.name !== Circle.name) {
			throw new Error('circle must be a Circle type')
		}
		this._circle = circle
	}

	/**
	 * Member id
	 */
	get id(): string {
		return this._data.id
	}

	/**
	 * Single uid
	 */
	get singleId(): string {
		return this._data.singleId
	}

	/**
	 * Formatted display name
	 */
	get displayName(): string {
		return this._data.displayName
	}

	/**
	 * Member userId
	 */
	get userId(): string {
		return this._data.userId
	}

	/**
	 * Member type
	 */
	get userType(): MemberType {
		// If the user type is a circle, this could originate from multiple sources
		return this._data.userType !== MemberTypes.CIRCLE
			? this._data.userType
			: this.basedOn.source
	}

	/**
	 * Member based on source
	 */
	get basedOn(): any {
		return this._data.basedOn
	}

	/**
	 * Member level
	 *
	 */
	get level(): MemberLevel {
		return this._data.level
	}

	/**
	 * Set member level
	 */
	set level(level: MemberLevel) {
		if (!(level in MemberLevels)) {
			throw new Error('Invalid level')
		}
		this._data.level = level
	}

	/**
	 * Member request status
	 *
	 */
	get status(): string {
		return this._data.status
	}

	/**
	 * Is the current member a user?
	 */
	get isUser() {
		return this._data.userType === MemberLevels.MEMBER
	}

	/**
	 * Is the current member without a circle?
	 */
	get isOrphan() {
		return this._circle?.constructor?.name !== Circle.name
	}

	/**
	 * Delete this member and any reference from its circle
	 */
	delete() {
		if (this.isOrphan) {
			throw new Error('Cannot delete this member as it doesn\'t belong to any circle')
		}
		this.circle.deleteMember(this)
		this._data = undefined
	}
}
