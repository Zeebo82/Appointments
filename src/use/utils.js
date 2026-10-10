import {linkTo} from "@nextcloud/router";
import SettingsService2 from "../services/SettingsService2";
import axios from "@nextcloud/axios";
import {showError} from "@nextcloud/dialogs";

export async function getTimezone(pageId, calId) {
	const service = new SettingsService2()
	try {
		const res = await service.getCalendarTimezone(pageId, calId)
		if (res === null || typeof res.data !== 'string' || res.data.trim() === '') {
			throw new Error('Calendar timezone could not be determined')
		}

		let userTz = res.data.trim()
		if (userTz.toUpperCase() === 'UTC') {
			return {name: 'UTC', data: 'UTC'}
		}

		const zonesResponse = await axios.get(linkTo('appointments', 'ajax/zones.js'))
		if (zonesResponse.status !== 200
			|| typeof zonesResponse.data !== 'object'
			|| !zonesResponse.data
			|| !zonesResponse.data.zones
			|| !zonesResponse.data.aliases) {
			throw new Error('Bad timezone database response')
		}

		const tzData = zonesResponse.data
		let zone = tzData.zones[userTz]
		if (zone === undefined && tzData.aliases[userTz] !== undefined) {
			userTz = tzData.aliases[userTz].aliasTo
			zone = tzData.zones[userTz]
		}
		if (!zone || !Array.isArray(zone.ics) || zone.ics.length === 0) {
			throw new Error('Timezone is not available: ' + userTz)
		}

		return {
			name: userTz,
			data: "BEGIN:VTIMEZONE\r\nTZID:" + userTz
				+ "\r\n" + zone.ics.join("\r\n").trim()
				+ "\r\nEND:VTIMEZONE"
		}
	} catch (err) {
		console.error('getTimezone error:', err)
		return null
	}
}

export function parsePageUrls(urlsData) {
	const arr = urlsData.split(String.fromCharCode(31))
	if (arr.length < 2) {
		console.error('bad urlsData:', urlsData)
		showError('error: can not parse page URL')
		return null;
	} else {
		return arr
	}
}