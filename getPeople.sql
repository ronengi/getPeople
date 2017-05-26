select *
from
  users
  inner join
  (
  select id, name,
    round(sqrt(pow((x - 3476), 2) + pow((y - 3207), 2))) as TA_distance,
    round(sqrt(pow((x - 3497), 2) + pow((y - 3279), 2))) as H_distance
  from cities
  )
  as distances

  on users.cityId = distances.id
where TA_distance < 50 OR H_distance < 110 ;





select users.name user, distances.name city, TA_distance, H_distance
from
  users
  inner join
  (
  select id, name,
    round(sqrt(pow((x - 3476), 2) + pow((y - 3207), 2))) as TA_distance,
    round(sqrt(pow((x - 3497), 2) + pow((y - 3279), 2))) as H_distance
  from cities
  )
  as distances

  on users.cityId = distances.id
where TA_distance < 50 OR H_distance < 110
