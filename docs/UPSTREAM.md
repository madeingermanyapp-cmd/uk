# Upstream provenance

VoxelBooking upstream: https://github.com/NowSquare/VoxelBooking

Pinned commit: `cbf54db39db583d401f60f9a5dbbaefb46ddc143` (version file 1.3.0).

History is retained. `upstream` points to NowSquare/VoxelBooking; `origin` to madeingermanyapp-cmd/uk. Wonham changes are on develop until critical tests pass. Preserve LICENSE, attribution and a visible source link. Offer the corresponding source of the deployed modified application under AGPL-3.0, excluding secrets and private records. The business must arrange contributor IP assignment separately.

The Wonham extension reuses VoxelBooking authentication, customer records, resources, booking creation, audit logs, database migrations and templates. It introduces enquiry/quote/payment records and shared inventory. Legacy booking writes are deliberately disabled in this deployment by Manor\Guard until they can use the same inventory rules. Existing upstream APIs remain in source but cannot mutate inventory through this release.
