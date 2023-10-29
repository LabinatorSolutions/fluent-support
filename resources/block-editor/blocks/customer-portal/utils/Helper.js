export const getColor = color => {
    let colorVal;
    if( color !== undefined && color.hex && color.color._a === 1 ) {
        colorVal = color.hex;
    } else if(color !== undefined && color.rgb && color.color._a !== 1) {
        colorVal = `rgba(${color.rgb.r}, ${color.rgb.g}, ${color.rgb.b}, ${color.rgb.a})`;
    } else if (color !== undefined && color.hex) {
        colorVal = color.hex;
    } else if (typeof color === "object" && color.hex !== '' ) {
        colorVal = `rgba(${color.r}, ${color.g}, ${color.b}, ${color.a})`;
    }  else if (typeof color === "string" && color[2] === "r") {
        color = JSON.parse(color);
        colorVal = `rgba(${color.r}, ${color.g}, ${color.b}, ${color.a})`;
    } else if (typeof color === "string" && color !== undefined ) {
        colorVal = color;
    }
    return colorVal;
};

export const colors = [
    { color: "#A7366F", name: "Maroon" },
    { color: "#474747", name: "Charcoal" },
    { color: "#F2AF29", name: "Bright Sun" },
    { color: "#065143", name: "Cyprus" },
    { color: "#129490", name: "Java" },
];
